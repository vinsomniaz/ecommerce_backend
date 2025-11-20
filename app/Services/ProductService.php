<?php
// app/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use App\Exceptions\Products\ProductNotFoundException;
use App\Exceptions\Products\ProductAlreadyExistsException;
use App\Exceptions\Products\ProductInUseException;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ProductService
{
    /**
     * Crear un nuevo producto
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['sku']) && Product::where('sku', $data['sku'])->exists()) {
                throw new ProductAlreadyExistsException($data['sku']);
            }

            if (empty($data['sku'])) {
                $data['sku'] = $this->generateUniqueSku();
            }

            // Extraer atributos y precios antes de crear el producto
            $attributes = $data['attributes'] ?? [];
            $warehousePrices = $data['warehouse_prices'] ?? []; // ✅ NUEVO
            unset($data['attributes'], $data['warehouse_prices']); // ✅ ACTUALIZADO

            $data = $this->setDefaultValues($data);
            $product = Product::create($data);

            // Crear atributos si existen
            if (!empty($attributes)) {
                $this->syncAttributes($product, $attributes);
            }

            // ✅ NUEVO: Asignar producto a todos los almacenes CON PRECIOS
            $this->assignProductToAllWarehouses($product, $warehousePrices);

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties($data)
                ->log('Producto creado y asignado a almacenes');

            return $product->fresh(['attributes', 'category', 'inventory.warehouse']);
        });
    }


    /**
     * Actualizar un producto existente
     */
    public function updateProduct(int $id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::findOrFail($id);

            $oldData = $product->toArray();

            // Validar SKU único si cambió
            if (isset($data['sku']) && $data['sku'] !== $product->sku) {
                if (
                    Product::where('sku', $data['sku'])
                    ->where('id', '!=', $id)
                    ->exists()
                ) {
                    throw new ProductAlreadyExistsException($data['sku']);
                }
            }

            // ✅ Extraer atributos y precios antes de actualizar
            $attributes = $data['attributes'] ?? null;
            $warehousePrices = $data['warehouse_prices'] ?? null;
            unset($data['attributes'], $data['warehouse_prices']);

            // Actualizar solo los campos enviados
            $product->update($data);

            // ✅ Sincronizar atributos si se enviaron
            if ($attributes !== null) {
                $this->syncAttributes($product, $attributes);
            }

            // ✅ Actualizar precios por almacén si se enviaron
            if ($warehousePrices !== null) {
                $this->updateWarehousePrices($product, $warehousePrices);
            }

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $product->toArray(),
                    'changed_fields' => array_keys($data),
                    'prices_updated' => $warehousePrices !== null,
                ])
                ->log('Producto actualizado');

            Log::info('Producto actualizado', [
                'id' => $product->id,
                'changed_fields' => array_keys($data),
                'prices_updated' => $warehousePrices !== null,
            ]);

            return $product->fresh(['attributes', 'category', 'media', 'inventory.warehouse']);
        });
    }


    /**
     * Eliminar un producto (soft delete)
     */
    public function delete(Product $product): bool
    {
        if ($this->hasTransactions($product)) {
            throw new ProductInUseException(
                'El producto tiene movimientos de inventario, lotes de compra o ventas asociadas'
            );
        }

        $product->delete();

        activity()
            ->performedOn($product)
            ->causedBy(Auth::user())
            ->log('Producto eliminado (soft delete)');

        return true;
    }

    /**
     * Restaurar un producto eliminado
     */
    public function restore(int $productId): Product
    {
        $product = Product::withTrashed()->findOrFail($productId);
        $product->restore();

        activity()
            ->performedOn($product)
            ->causedBy(Auth::user())
            ->log('Producto restaurado');

        return $product->fresh(['attributes', 'category']);
    }

    /**
     * Eliminar permanentemente un producto
     */
    public function forceDelete(Product $product): bool
    {
        $product->clearMediaCollection('images');
        $product->forceDelete();

        activity()
            ->causedBy(Auth::user())
            ->withProperties(['product_id' => $product->id])
            ->log('Producto eliminado permanentemente');

        return true;
    }

    /**
     * Subir imágenes a un producto con conversiones automáticas
     */
    public function uploadImages(Product $product, array $images): array
    {
        return DB::transaction(function () use ($product, $images) {
            $currentCount = $product->getMedia('images')->count();
            $newCount = count($images);

            if (($currentCount + $newCount) > 5) {
                throw new \Exception(
                    "No puede tener más de 5 imágenes. Actualmente tiene {$currentCount}. " .
                        "Solo puede agregar " . (5 - $currentCount) . " más."
                );
            }

            $uploadedImages = [];
            $isFirstImage = $currentCount === 0;
            foreach ($images as $index => $image) {
                // Generar nombre único basado en el producto y timestamp
                $fileName = sprintf(
                    '%s-%s-%d',
                    \Illuminate\Support\Str::slug($product->primary_name),
                    time(),
                    $index + 1
                );

                $media = $product->addMedia($image)
                    ->usingName($product->primary_name)
                    ->usingFileName($fileName . '.' . $image->getClientOriginalExtension())
                    ->withCustomProperties([
                        'order' => $currentCount + $index + 1,
                        'is_primary' => $isFirstImage && $index === 0,
                    ])
                    ->toMediaCollection('images', 'public');

                $uploadedImages[] = [
                    'id' => $media->id,
                    'name' => $media->file_name,
                    'original_url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb'),
                    'medium_url' => $media->getUrl('medium'),
                    'large_url' => $media->getUrl('large'),
                    'size' => $this->formatBytes($media->size),
                    'order' => $media->getCustomProperty('order'),
                    'is_primary' => $media->getCustomProperty('is_primary', false),
                ];
            }

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties(['images_count' => $newCount])
                ->log("Se agregaron {$newCount} imágenes al producto");

            return $uploadedImages;
        });
    }

    /**
     * Eliminar una imagen específica
     */
    public function deleteImage(Product $product, int $mediaId): bool
    {
        return DB::transaction(function () use ($product, $mediaId) {

            // Lee por query directa (evita cache de getMedia)
            $media = Media::query()
                ->where('model_type', Product::class)
                ->where('model_id', $product->id)
                ->whereKey($mediaId)
                ->first();

            if (!$media) {
                throw new \Exception('Imagen no encontrada');
            }

            $wasPrimary = $media->getCustomProperty('is_primary', false);

            // Elimina registro + archivos
            $media->delete();

            // ⚠️ Limpia la relación cacheada en el modelo actual
            $product->unsetRelation('media');

            // Relee desde BD las restantes
            $remaining = Media::query()
                ->where('model_type', Product::class)
                ->where('model_id', $product->id)
                ->orderBy('id')
                ->get();

            // Si era principal, marca la primera restante como principal
            if ($wasPrimary && $remaining->isNotEmpty()) {
                $first = $remaining->first();
                $first->setCustomProperty('is_primary', true);
                $first->save();
            }

            // Reordena
            foreach ($remaining->values() as $index => $item) {
                $item->setCustomProperty('order', $index + 1);
                $item->save();
            }

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties(['media_id' => $mediaId])
                ->log('Imagen eliminada del producto');

            return true;
        });
    }

    /**
     * Eliminar múltiples imágenes (mantener para compatibilidad)
     */
    public function deleteImages(Product $product, array $mediaIds): Product
    {
        DB::transaction(function () use ($product, $mediaIds) {
            foreach ($mediaIds as $mediaId) {
                try {
                    $this->deleteImage($product, $mediaId);
                } catch (\Exception $e) {
                    // Continuar con las siguientes imágenes
                }
            }
        });

        return $product->fresh();
    }

    /**
     * Actualizar estado de un producto
     */
    public function updateStatus(Product $product, string $field, bool $value): Product
    {
        $allowedFields = ['is_active', 'is_featured', 'visible_online'];

        if (!in_array($field, $allowedFields)) {
            throw new InvalidArgumentException("Campo '{$field}' no permitido para actualización de estado");
        }

        $product->update([$field => $value]);

        activity()
            ->performedOn($product)
            ->causedBy(Auth::user())
            ->log("Estado {$field} actualizado a " . ($value ? 'true' : 'false'));

        return $product->fresh();
    }

    /**
     * Actualización masiva de productos
     */
    public function bulkUpdate(array $productIds, string $action): int
    {
        return DB::transaction(function () use ($productIds, $action) {
            $query = Product::whereIn('id', $productIds);
            $count = $query->count();

            $updated = match ($action) {
                'activate' => $query->update(['is_active' => true]),
                'deactivate' => $query->update(['is_active' => false]),
                'feature' => $query->update(['is_featured' => true]),
                'unfeature' => $query->update(['is_featured' => false]),
                'show_online' => $query->update(['visible_online' => true]),
                'hide_online' => $query->update(['visible_online' => false]),
                'mark_new' => $query->update(['is_new' => true]), // ✅ AGREGAR
                'unmark_new' => $query->update(['is_new' => false]), // ✅ AGREGAR
                'delete' => $query->delete(),
                default => throw new InvalidArgumentException("Acción '{$action}' no válida"),
            };

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'action' => $action,
                    'product_ids' => $productIds,
                    'affected_count' => $count,
                ])
                ->log("Actualización masiva de productos: {$action}");

            return $count;
        });
    }

    /**
     * Obtener productos con filtros (ACTUALIZADO para sistema de lotes)
     */
    public function getFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'media', 'attributes']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('primary_name', 'like', "%{$search}%")
                    ->orWhere('secondary_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        // ✅ CORRECCIÓN: Convertir strings a booleanos antes de filtrar
        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_featured', $filters)) {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('visible_online', $filters)) {
            $query->where('visible_online', filter_var($filters['visible_online'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_new', $filters)) {
            $query->where('is_new', filter_var($filters['is_new'], FILTER_VALIDATE_BOOLEAN));
        }

        // Filtrar por almacén específico
        if (!empty($filters['warehouse_id'])) {
            $query->whereHas('inventory', function ($q) use ($filters) {
                $q->where('warehouse_id', $filters['warehouse_id']);
            });
        }

        // Filtrar productos con stock
        if (!empty($filters['with_stock'])) {
            $query->whereHas('inventory', function ($q) {
                $q->where('available_stock', '>', 0);
            });
        }

        // Filtrar productos con stock bajo
        if (!empty($filters['low_stock'])) {
            $query->whereHas('inventory', function ($q) {
                $q->whereColumn('available_stock', '<=', 'products.min_stock');
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validar que el campo de ordenamiento existe
        $allowedSortFields = ['created_at', 'updated_at', 'primary_name', 'sku', 'brand'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        if (!empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        return $query->paginate($perPage);
    }
    /**
     * Obtener estadísticas de productos (ACTUALIZADO)
     */
    public function getStatistics(): array
    {
        // Calcular valor total de inventario desde lotes activos
        $totalInventoryValue = DB::table('purchase_batches')
            ->where('status', 'active')
            ->sum(DB::raw('quantity_available * distribution_price'));

        // Calcular valor de costo total
        $totalCostValue = DB::table('purchase_batches')
            ->where('status', 'active')
            ->sum(DB::raw('quantity_available * purchase_price'));

        // Stock total disponible
        $totalStock = DB::table('inventory')->sum('available_stock');

        // Productos con stock bajo
        $lowStockProducts = Product::whereHas('inventory', function ($q) {
            $q->whereColumn('available_stock', '<=', 'products.min_stock');
        })->count();

        // Productos sin stock
        $outOfStockProducts = Product::whereDoesntHave('inventory', function ($q) {
            $q->where('available_stock', '>', 0);
        })->count();

        return [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'featured_products' => Product::where('is_featured', true)->count(),
            'online_products' => Product::where('visible_online', true)->count(),
            'trashed_products' => Product::onlyTrashed()->count(),

            // Estadísticas de inventario
            'total_stock' => (int) $totalStock,
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_products' => $outOfStockProducts,

            // Valores monetarios
            'total_inventory_value' => round($totalInventoryValue, 2),
            'total_cost_value' => round($totalCostValue, 2),
            'potential_profit' => round($totalInventoryValue - $totalCostValue, 2),

            // Otros
            'brands_count' => Product::distinct('brand')->whereNotNull('brand')->count('brand'),
            'categories_count' => Product::distinct('category_id')->count('category_id'),
            'products_with_batches' => Product::has('purchaseBatches')->count(),
        ];
    }

    /**
     * Duplicar un producto
     */
    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $newProduct = $product->replicate();
            $newProduct->sku = $this->generateUniqueSku();
            $newProduct->primary_name = $product->primary_name . ' (Copia)';
            $newProduct->is_active = false;
            $newProduct->save();

            // Copiar imágenes
            foreach ($product->getMedia('images') as $media) {
                $newProduct->addMediaFromUrl($media->getUrl())
                    ->toMediaCollection('images');
            }

            // ✅ Duplicar atributos SIN tocar la propiedad protegida $attributes
            $productAttributes = $product->attributes()->get();

            foreach ($productAttributes as $attribute) {
                $newProduct->attributes()->create([
                    'name' => $attribute->name,
                    'value' => $attribute->value,
                ]);
            }

            activity()
                ->performedOn($newProduct)
                ->causedBy(Auth::user())
                ->withProperties(['original_id' => $product->id])
                ->log('Producto duplicado');

            return $newProduct->fresh(['attributes', 'category']);
        });
    }

    // ==================== MÉTODOS PRIVADOS ====================
    private function assignProductToAllWarehouses(Product $product, array $warehousePrices = []): void
    {
        // Obtener todos los almacenes activos
        $warehouses = Warehouse::active()->get();

        if ($warehouses->isEmpty()) {
            Log::warning("No hay almacenes activos para asignar el producto #{$product->id}");
            return;
        }

        // ✅ NUEVO: Crear un mapa de precios por warehouse_id
        $pricesMap = collect($warehousePrices)->keyBy('warehouse_id');

        foreach ($warehouses as $warehouse) {
            // ✅ NUEVO: Obtener precios específicos del almacén o usar 0.00 por defecto
            $warehousePriceData = $pricesMap->get($warehouse->id);

            $salePrice = $warehousePriceData['sale_price'] ?? 0.00;
            $minSalePrice = $warehousePriceData['min_sale_price'] ?? 0.00;

            Inventory::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'available_stock' => 0,
                'reserved_stock' => 0,
                'sale_price' => $salePrice,           // ✅ ACTUALIZADO
                'min_sale_price' => $minSalePrice,    // ✅ ACTUALIZADO
                'profit_margin' => 0.00,
                'last_movement_at' => null,
            ]);
        }

        Log::info("Producto #{$product->id} asignado a {$warehouses->count()} almacenes con precios");
    }

    private function updateWarehousePrices(Product $product, array $warehousePrices): void
    {
        foreach ($warehousePrices as $priceData) {
            $warehouseId = $priceData['warehouse_id'];
            $salePrice = $priceData['sale_price'];
            $minSalePrice = $priceData['min_sale_price'];

            // Buscar o crear el inventario para este almacén
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'available_stock' => 0,
                    'reserved_stock' => 0,
                    'sale_price' => $salePrice,
                    'min_sale_price' => $minSalePrice,
                    'profit_margin' => 0.00,
                    'last_movement_at' => null,
                ]
            );

            // Si ya existe, actualizar solo los precios
            if (!$inventory->wasRecentlyCreated) {
                $inventory->update([
                    'sale_price' => $salePrice,
                    'min_sale_price' => $minSalePrice,
                ]);
            }
        }

        Log::info("Precios actualizados para producto #{$product->id}", [
            'warehouses_count' => count($warehousePrices),
        ]);
    }
    /**
     * Verificar si el producto tiene transacciones
     */
    private function hasTransactions(Product $product): bool
    {
        try {
            // Verificar movimientos de stock
            if (method_exists($product, 'stockMovements') && $product->stockMovements()->exists()) {
                return true;
            }

            // Verificar lotes de compra
            if (method_exists($product, 'purchaseBatches') && $product->purchaseBatches()->exists()) {
                return true;
            }

            // Verificar inventario con stock
            if ($product->inventory()->where('available_stock', '>', 0)->exists()) {
                return true;
            }
        } catch (\Exception $e) {
            // Tabla aún no existe
        }

        return false;
    }

    /**
     * Generar SKU único
     */
    private function generateUniqueSku(): string
    {
        do {
            $sku = 'PRD-' . strtoupper(uniqid());
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * Sincronizar atributos del producto
     */
    private function syncAttributes(Product $product, array $attributes): void
    {
        // Eliminar atributos existentes
        $product->attributes()->delete();

        // Crear nuevos atributos
        foreach ($attributes as $attribute) {
            if (!empty($attribute['name']) && !empty($attribute['value'])) {
                $product->attributes()->create([
                    'name' => $attribute['name'],
                    'value' => $attribute['value'],
                ]);
            }
        }
    }

    /**
     * Establecer valores por defecto
     */
    private function setDefaultValues(array $data): array
    {
        $defaults = [
            'min_stock' => 5,
            'unit_measure' => 'NIU',
            'tax_type' => '10',
            'is_active' => true,
            'is_featured' => false,
            'visible_online' => true,
            'is_new' => false, // ✅ AGREGAR ESTA LÍNEA
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Formatear bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

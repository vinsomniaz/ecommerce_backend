<?php
// app/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use App\Exceptions\Products\ProductNotFoundException;
use App\Exceptions\Products\ProductAlreadyExistsException;
use App\Exceptions\Products\ProductInUseException;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\PriceList;
use App\Models\ProductPrice;
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

            // ✅ Extraer atributos y precios
            $attributes = $data['attributes'] ?? [];
            $prices = $data['prices'] ?? [];
            unset($data['attributes'], $data['prices']);

            $data = $this->setDefaultValues($data);
            $product = Product::create($data);

            // Sincronizar atributos
            if (!empty($attributes)) {
                $this->syncAttributes($product, $attributes);
            }

            // ✅ Asignar a almacenes
            $this->assignProductToAllWarehouses($product);

            // ✅ Crear precios si se enviaron
            if (!empty($prices)) {
                $this->syncPrices($product, $prices);
            }

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties([
                    'data' => $data,
                    'prices_count' => count($prices),
                ])
                ->log('Producto creado con precios y asignado a almacenes');

            return $product->fresh(['attributes', 'category', 'inventory.warehouse', 'productPrices.priceList']);
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

            if (isset($data['sku']) && $data['sku'] !== $product->sku) {
                if (Product::where('sku', $data['sku'])->where('id', '!=', $id)->exists()) {
                    throw new ProductAlreadyExistsException($data['sku']);
                }
            }

            // ✅ Extraer atributos y precios
            $attributes = $data['attributes'] ?? null;
            $prices = $data['prices'] ?? null;
            unset($data['attributes'], $data['prices']);

            $product->update($data);

            // Sincronizar atributos si se enviaron
            if ($attributes !== null) {
                $this->syncAttributes($product, $attributes);
            }

            // ✅ Sincronizar precios si se enviaron
            if ($prices !== null) {
                $this->syncPrices($product, $prices);
            }

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $product->toArray(),
                    'changed_fields' => array_keys($data),
                    'prices_updated' => $prices !== null,
                ])
                ->log('Producto actualizado');

            return $product->fresh([
                'attributes',
                'category',
                'media',
                'inventory.warehouse',
                'productPrices.priceList',
                'productPrices.warehouse'
            ]);
        });
    }


    /**
     * ✅ Sincronizar precios del producto
     */
    private function syncPrices(Product $product, array $prices): void
    {
        $priceIds = [];

        foreach ($prices as $priceData) {
            if (!empty($priceData['id'])) {
                // Actualizar existente
                $price = ProductPrice::where('id', $priceData['id'])
                    ->where('product_id', $product->id)
                    ->first();

                if ($price) {
                    $price->update([
                        'price_list_id' => $priceData['price_list_id'],
                        'warehouse_id' => $priceData['warehouse_id'] ?? null,
                        'price' => $priceData['price'],
                        'min_price' => $priceData['min_price'] ?? null,
                        'currency' => $priceData['currency'] ?? 'PEN',
                        'min_quantity' => $priceData['min_quantity'] ?? 1,
                        'valid_from' => $priceData['valid_from'] ?? now(),
                        'valid_to' => $priceData['valid_to'] ?? null,
                        'is_active' => $priceData['is_active'] ?? true,
                    ]);
                    $priceIds[] = $price->id;
                }
            } else {
                // Crear o actualizar si ya existe
                $existingPrice = ProductPrice::where('product_id', $product->id)
                    ->where('price_list_id', $priceData['price_list_id'])
                    ->where('warehouse_id', $priceData['warehouse_id'] ?? null)
                    ->first();

                if ($existingPrice) {
                    $existingPrice->update([
                        'price' => $priceData['price'],
                        'min_price' => $priceData['min_price'] ?? null,
                        'currency' => $priceData['currency'] ?? 'PEN',
                        'min_quantity' => $priceData['min_quantity'] ?? 1,
                        'valid_from' => $priceData['valid_from'] ?? now(),
                        'valid_to' => $priceData['valid_to'] ?? null,
                        'is_active' => $priceData['is_active'] ?? true,
                    ]);
                    $priceIds[] = $existingPrice->id;
                } else {
                    $newPrice = ProductPrice::create([
                        'product_id' => $product->id,
                        'price_list_id' => $priceData['price_list_id'],
                        'warehouse_id' => $priceData['warehouse_id'] ?? null,
                        'price' => $priceData['price'],
                        'min_price' => $priceData['min_price'] ?? null,
                        'currency' => $priceData['currency'] ?? 'PEN',
                        'min_quantity' => $priceData['min_quantity'] ?? 1,
                        'valid_from' => $priceData['valid_from'] ?? now(),
                        'valid_to' => $priceData['valid_to'] ?? null,
                        'is_active' => $priceData['is_active'] ?? true,
                    ]);
                    $priceIds[] = $newPrice->id;
                }
            }
        }

        // ELIMINAR precios que no están en el array
        if (!empty($priceIds)) {
            ProductPrice::where('product_id', $product->id)
                ->whereNotIn('id', $priceIds)
                ->delete();
        }

        Log::info("Precios sincronizados", [
            'product_id' => $product->id,
            'prices_count' => count($priceIds),
        ]);
    }

    /**
     * ✅ Eliminar precio específico de un producto
     *
     * @param Product $product
     * @param int $priceId
     * @return bool
     */
    public function deletePrice(Product $product, int $priceId): bool
    {
        $price = ProductPrice::where('id', $priceId)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $deleted = $price->delete();

        if ($deleted) {
            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties([
                    'price_id' => $priceId,
                    'price_list_id' => $price->price_list_id,
                    'warehouse_id' => $price->warehouse_id,
                ])
                ->log('Precio eliminado del producto');
        }

        return $deleted;
    }

    /**
     * ✅ Obtener todos los precios de un producto agrupados por lista
     *
     * @param Product $product
     * @param int|null $warehouseId
     * @return array
     */
    public function getProductPrices(Product $product, ?int $warehouseId = null): array
    {
        $query = $product->productPrices()
            ->with(['priceList:id,code,name', 'warehouse:id,name'])
            ->where('is_active', true)
            ->orderBy('price_list_id')
            ->orderBy('warehouse_id');

        if ($warehouseId) {
            $query->where(function ($q) use ($warehouseId) {
                $q->whereNull('warehouse_id')
                    ->orWhere('warehouse_id', $warehouseId);
            });
        }

        return $query->get()->map(function ($price) {
            return [
                'id' => $price->id,
                'price_list_id' => $price->price_list_id,
                'price_list_code' => $price->priceList->code,
                'price_list_name' => $price->priceList->name,
                'warehouse_id' => $price->warehouse_id,
                'warehouse_name' => $price->warehouse?->name,
                'price' => (float) $price->price,
                'min_price' => (float) $price->min_price,
                'currency' => $price->currency,
                'min_quantity' => $price->min_quantity,
                'valid_from' => $price->valid_from?->toDateTimeString(),
                'valid_to' => $price->valid_to?->toDateTimeString(),
                'is_active' => $price->is_active,
                'is_currently_valid' => $price->isCurrentlyValid(),
                'scope' => $price->getScopeDescription(),
            ];
        })->toArray();
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
     * Establecer una imagen como principal
     */
    public function setPrimaryImage(Product $product, int $mediaId): array
    {
        return DB::transaction(function () use ($product, $mediaId) {

            // Obtener todas las imágenes del producto
            $media = Media::query()
                ->where('model_type', Product::class)
                ->where('model_id', $product->id)
                ->where('collection_name', 'images')
                ->get();

            if ($media->isEmpty()) {
                throw new \Exception('El producto no tiene imágenes');
            }

            // Verificar que la imagen existe
            $targetMedia = $media->firstWhere('id', $mediaId);

            if (!$targetMedia) {
                throw new \Exception('Imagen no encontrada');
            }

            // Remover la propiedad is_primary de todas las imágenes
            foreach ($media as $item) {
                $item->setCustomProperty('is_primary', false);
                $item->save();
            }

            // Establecer la nueva imagen principal
            $targetMedia->setCustomProperty('is_primary', true);
            $targetMedia->save();

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties([
                    'media_id' => $mediaId,
                    'action' => 'set_primary_image'
                ])
                ->log('Imagen principal actualizada');

            Log::info('Imagen principal actualizada', [
                'product_id' => $product->id,
                'media_id' => $mediaId,
            ]);

            // Retornar todas las imágenes actualizadas
            return $media->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->file_name,
                    'original_url' => $item->getUrl(),
                    'thumb_url' => $item->getUrl('thumb'),
                    'medium_url' => $item->getUrl('medium'),
                    'large_url' => $item->getUrl('large'),
                    'size' => $this->formatBytes($item->size),
                    'order' => $item->getCustomProperty('order'),
                    'is_primary' => $item->getCustomProperty('is_primary', false),
                ];
            })->toArray();
        });
    }

    /**
     * Reordenar imágenes del producto
     */
    public function reorderImages(Product $product, array $imageOrder): array
    {
        return DB::transaction(function () use ($product, $imageOrder) {

            // $imageOrder debe ser un array como: [3, 1, 5, 2, 4]
            // donde cada número es el ID de la imagen en el orden deseado

            foreach ($imageOrder as $index => $mediaId) {
                $media = Media::query()
                    ->where('model_type', Product::class)
                    ->where('model_id', $product->id)
                    ->where('id', $mediaId)
                    ->first();

                if ($media) {
                    $media->setCustomProperty('order', $index + 1);
                    $media->save();
                }
            }

            activity()
                ->performedOn($product)
                ->causedBy(Auth::user())
                ->withProperties(['new_order' => $imageOrder])
                ->log('Orden de imágenes actualizado');

            // Retornar imágenes ordenadas
            $media = Media::query()
                ->where('model_type', Product::class)
                ->where('model_id', $product->id)
                ->whereIn('id', $imageOrder)
                ->get()
                ->sortBy(function ($item) use ($imageOrder) {
                    return array_search($item->id, $imageOrder);
                });

            return $media->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->file_name,
                    'original_url' => $item->getUrl(),
                    'thumb_url' => $item->getUrl('thumb'),
                    'medium_url' => $item->getUrl('medium'),
                    'large_url' => $item->getUrl('large'),
                    'size' => $this->formatBytes($item->size),
                    'order' => $item->getCustomProperty('order'),
                    'is_primary' => $item->getCustomProperty('is_primary', false),
                ];
            })->values()->toArray();
        });
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
        $query = Product::query()->with([
            'category',
            'media',
            'attributes',
            'inventory' => function ($q) {
                $q->with('warehouse:id,name,is_main')
                    ->orderBy('warehouse_id');
            }
        ]);

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

        // ✅ NUEVO: Filtro por categoría (incluyendo subcategorías)
        if (!empty($filters['category_id'])) {
            $categoryId = $filters['category_id'];

            // Cargar la categoría con sus hijos
            $category = Category::with('children.children')->find($categoryId);

            if ($category) {
                // Obtener todos los IDs de categorías (padre + hijos + nietos)
                $categoryIds = $category->getAllDescendantIdsWithCache();

                // Filtrar productos que pertenezcan a cualquiera de esas categorías
                $query->whereIn('category_id', $categoryIds);

                Log::info('Filtro de categoría aplicado en productos', [
                    'category_id' => $categoryId,
                    'category_name' => $category->name,
                    'included_category_ids' => $categoryIds,
                    'total_categories' => count($categoryIds)
                ]);
            } else {
                Log::warning('Categoría no encontrada para filtro de productos', ['category_id' => $categoryId]);
            }
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        // Convertir strings a booleanos antes de filtrar
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
        // Calcular valor desde product_prices (lista por defecto)
        $defaultPriceList = PriceList::where('is_active', true)
            ->orderBy('id')
            ->first();

        $totalInventoryValue = 0;

        if ($defaultPriceList) {
            $inventories = Inventory::where('available_stock', '>', 0)->get();

            foreach ($inventories as $inv) {
                $price = $inv->getSalePrice($defaultPriceList->id);
                if ($price) {
                    $totalInventoryValue += $inv->available_stock * $price;
                }
            }
        }

        $totalCostValue = Inventory::where('available_stock', '>', 0)
            ->selectRaw('SUM(available_stock * average_cost) as total')
            ->value('total') ?? 0;

        return [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'featured_products' => Product::where('is_featured', true)->count(),
            'online_products' => Product::where('visible_online', true)->count(),
            'trashed_products' => Product::onlyTrashed()->count(),

            'total_stock' => (int) DB::table('inventory')->sum('available_stock'),
            'low_stock_products' => Product::whereHas('inventory', function ($q) {
                $q->whereColumn('available_stock', '<=', 'products.min_stock');
            })->count(),
            'out_of_stock_products' => Product::whereDoesntHave('inventory', function ($q) {
                $q->where('available_stock', '>', 0);
            })->count(),

            'total_inventory_value' => round($totalInventoryValue, 2),
            'total_cost_value' => round($totalCostValue, 2),
            'potential_profit' => round($totalInventoryValue - $totalCostValue, 2),

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
    private function assignProductToAllWarehouses(Product $product): void
    {
        $warehouses = Warehouse::active()->get();

        if ($warehouses->isEmpty()) {
            Log::warning("No hay almacenes activos para asignar el producto #{$product->id}");
            return;
        }

        foreach ($warehouses as $warehouse) {
            Inventory::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'available_stock' => 0,
                'reserved_stock' => 0,
                'average_cost' => 0.00, // Se actualiza cuando hay compras
                'price_updated_at' => null,
                'last_movement_at' => null,
            ]);
        }

        Log::info("Producto #{$product->id} asignado a {$warehouses->count()} almacén(es)");
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
        $attributeIds = [];

        foreach ($attributes as $attr) {
            if (empty($attr['name']) || empty($attr['value'])) {
                continue; // Saltar atributos vacíos
            }

            if (!empty($attr['id'])) {
                // Actualizar existente
                $existingAttr = $product->attributes()->find($attr['id']);

                if ($existingAttr) {
                    $existingAttr->update([
                        'name' => $attr['name'],
                        'value' => $attr['value'],
                    ]);
                    $attributeIds[] = $existingAttr->id;
                }
            } else {
                // Crear nuevo
                $newAttr = $product->attributes()->create([
                    'name' => $attr['name'],
                    'value' => $attr['value'],
                ]);
                $attributeIds[] = $newAttr->id;
            }
        }

        // 🔥 ELIMINAR atributos que no están en el array
        if (!empty($attributeIds)) {
            $product->attributes()->whereNotIn('id', $attributeIds)->delete();
        } else {
            // Si no hay atributos, eliminar todos
            $product->attributes()->delete();
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
            'distribution_price' => 0.00,
            'is_active' => true,
            'is_featured' => false,
            'visible_online' => true,
            'is_new' => false,
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

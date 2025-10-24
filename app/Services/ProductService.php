<?php
// app/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use App\Exceptions\Products\ProductNotFoundException;
use App\Exceptions\Products\ProductAlreadyExistsException;
use App\Exceptions\Products\ProductInUseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Crear un nuevo producto
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // Verificar si el SKU ya existe (si se proporciona)
            if (!empty($data['sku']) && Product::where('sku', $data['sku'])->exists()) {
                throw new ProductAlreadyExistsException($data['sku']);
            }

            // Generar SKU automático si no se proporciona
            if (empty($data['sku'])) {
                $data['sku'] = $this->generateUniqueSku();
            }

            // Establecer valores por defecto
            $data = $this->setDefaultValues($data);

            // Crear el producto
            $product = Product::create($data);

            // Registrar actividad
            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->withProperties($data)
                ->log('Producto creado');

            return $product->fresh();
        });
    }

    /**
     * Actualizar un producto existente
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $oldData = $product->toArray();

            // Verificar SKU único si se está actualizando
            if (isset($data['sku']) && $data['sku'] !== $product->sku) {
                if (Product::where('sku', $data['sku'])->where('id', '!=', $product->id)->exists()) {
                    throw new ProductAlreadyExistsException($data['sku']);
                }
            }

            $product->update($data);

            // Registrar actividad con cambios
            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $product->toArray(),
                ])
                ->log('Producto actualizado');

            return $product->fresh();
        });
    }

    /**
     * Eliminar un producto (soft delete)
     */
    public function delete(Product $product): bool
    {
        // Verificar si el producto tiene movimientos
        if ($this->hasTransactions($product)) {
            throw new ProductInUseException(
                'El producto tiene movimientos de inventario, ventas o compras asociadas'
            );
        }

        $product->delete();

        activity()
            ->performedOn($product)
            ->causedBy(auth()->user())
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
            ->causedBy(auth()->user())
            ->log('Producto restaurado');

        return $product;
    }

    /**
     * Eliminar permanentemente un producto
     */
    public function forceDelete(Product $product): bool
    {
        // Eliminar todas las imágenes asociadas
        $product->clearMediaCollection('images');

        $product->forceDelete();

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['product_id' => $product->id])
            ->log('Producto eliminado permanentemente');

        return true;
    }

    /**
     * Subir imágenes a un producto
     */
    public function uploadImages(Product $product, array $images): Product
    {
        DB::transaction(function () use ($product, $images) {
            $currentCount = $product->getMedia('images')->count();
            $newCount = count($images);

            if (($currentCount + $newCount) > 5) {
                throw new \Exception('No puede tener más de 5 imágenes. Actualmente tiene ' . $currentCount);
            }

            foreach ($images as $image) {
                $product->addMedia($image)
                    ->toMediaCollection('images');
            }

            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->log("Se agregaron {$newCount} imágenes al producto");
        });

        return $product->fresh();
    }

    /**
     * Eliminar imágenes de un producto
     */
    public function deleteImages(Product $product, array $mediaIds): Product
    {
        DB::transaction(function () use ($product, $mediaIds) {
            $deletedCount = 0;

            foreach ($mediaIds as $mediaId) {
                $media = $product->getMedia('images')->where('id', $mediaId)->first();
                if ($media) {
                    $media->delete();
                    $deletedCount++;
                }
            }

            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->log("Se eliminaron {$deletedCount} imágenes del producto");
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
            throw new \InvalidArgumentException("Campo '{$field}' no permitido para actualización de estado");
        }

        $product->update([$field => $value]);

        activity()
            ->performedOn($product)
            ->causedBy(auth()->user())
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
                'delete' => $query->delete(),
                default => throw new \InvalidArgumentException("Acción '{$action}' no válida"),
            };

            activity()
                ->causedBy(auth()->user())
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
     * Obtener productos con filtros
     */
    public function getFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query();

        // Búsqueda general
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('primary_name', 'like', "%{$search}%")
                    ->orWhere('secondary_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Filtros específicos
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['brand'])) {
            $query->where('brand', $filters['brand']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        if (isset($filters['visible_online'])) {
            $query->where('visible_online', $filters['visible_online']);
        }

        // Rango de precios
        if (!empty($filters['min_price'])) {
            $query->where('unit_price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('unit_price', '<=', $filters['max_price']);
        }

        // Ordenamiento
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Incluir eliminados
        if (!empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        return $query->paginate($perPage);
    }

    /**
     * Obtener estadísticas de productos
     */
    public function getStatistics(): array
    {
        return [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'featured_products' => Product::where('is_featured', true)->count(),
            'online_products' => Product::where('visible_online', true)->count(),
            'trashed_products' => Product::onlyTrashed()->count(),
            'total_inventory_value' => Product::sum(DB::raw('unit_price * min_stock')),
            'total_cost_value' => Product::sum(DB::raw('cost_price * min_stock')),
            'brands_count' => Product::distinct('brand')->whereNotNull('brand')->count('brand'),
            'categories_count' => Product::distinct('category_id')->count('category_id'),
        ];
    }

    /**
     * Verificar si el producto tiene transacciones
     */
    private function hasTransactions(Product $product): bool
    {
        // Verificar si tiene movimientos de inventario (solo si la tabla existe)
        try {
            if (method_exists($product, 'stockMovements') && $product->stockMovements()->exists()) {
                return true;
            }
        } catch (\Exception $e) {
            // Tabla aún no existe, continuar
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
            'cost_price' => 0,
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
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
            $newProduct->is_active = false; // Por seguridad, crear inactivo
            $newProduct->save();

            // Copiar imágenes
            foreach ($product->getMedia('images') as $media) {
                $newProduct->addMediaFromUrl($media->getUrl())
                    ->toMediaCollection('images');
            }

            activity()
                ->performedOn($newProduct)
                ->causedBy(auth()->user())
                ->withProperties(['original_id' => $product->id])
                ->log('Producto duplicado');

            return $newProduct;
        });
    }
}

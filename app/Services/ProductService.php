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
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

            $data = $this->setDefaultValues($data);
            $product = Product::create($data);

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

            if (isset($data['sku']) && $data['sku'] !== $product->sku) {
                if (Product::where('sku', $data['sku'])->where('id', '!=', $product->id)->exists()) {
                    throw new ProductAlreadyExistsException($data['sku']);
                }
            }

            $product->update($data);

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
        $product->clearMediaCollection('images');
        $product->forceDelete();

        activity()
            ->causedBy(auth()->user())
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
                ->causedBy(auth()->user())
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
                ->causedBy(auth()->user())
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

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('primary_name', 'like', "%{$search}%")
                    ->orWhere('secondary_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

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

        if (!empty($filters['min_price'])) {
            $query->where('unit_price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('unit_price', '<=', $filters['max_price']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

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

    // MÉTODOS PRIVADOS

    private function hasTransactions(Product $product): bool
    {
        try {
            if (method_exists($product, 'stockMovements') && $product->stockMovements()->exists()) {
                return true;
            }
        } catch (\Exception $e) {
            // Tabla aún no existe
        }

        return false;
    }

    private function generateUniqueSku(): string
    {
        do {
            $sku = 'PRD-' . strtoupper(uniqid());
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

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

<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ProductException;
use Illuminate\Support\Facades\Auth;


class ProductService
{
    public function createProduct(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Auto-generar SKU si no existe
            if (empty($data['sku'])) {
                $data['sku'] = $this->generateUniqueSKU();
            }

            // Valores por defecto
            $data['min_stock'] = $data['min_stock'] ?? 5;
            $data['unit_measure'] = $data['unit_measure'] ?? 'NIU';
            $data['tax_type'] = $data['tax_type'] ?? '10';
            $data['is_active'] = $data['is_active'] ?? true;
            $data['visible_online'] = $data['visible_online'] ?? false;

            $product = Product::create($data);

            // Crear inventario inicial en almacén principal
            // if (isset($data['initial_stock']) && $data['initial_stock'] > 0) {
            //     $mainWarehouse = Warehouse::where('is_main', true)->first();
            //     if ($mainWarehouse) {
            //         Inventory::create([
            //             'product_id' => $product->id,
            //             'warehouse_id' => $mainWarehouse->id,
            //             'available_stock' => $data['initial_stock'],
            //             'reserved_stock' => 0,
            //             'precio_venta' => $product->unit_price,
            //         ]);
            //     }
            // }

            // activity()
            //     ->performedOn($product)
            //     ->causedBy(auth()->user())
            //     ->log('Producto creado');

            return $product;
        });
    }

    public function updateProduct(Product $product, array $data)
    {
        return DB::transaction(function () use ($product, $data) {
            // Validar cambio de SKU con ventas
            if (isset($data['sku']) && $data['sku'] !== $product->sku) {
                if ($product->sales()->exists()) {
                    throw new ProductException('No se puede cambiar SKU con ventas asociadas');
                }
            }

            $product->update($data);

            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->log('Producto actualizado');

            return $product->fresh(['category', 'inventory']);
        });
    }

    public function deleteProduct(Product $product)
    {
        return DB::transaction(function () use ($product) {
            // Validar stock
            if ($product->inventory()->sum('available_stock') > 0) {
                throw new ProductException('No se puede eliminar producto con stock');
            }

            // Soft delete si tiene ventas, hard delete si no
            if ($product->sales()->exists()) {
                $product->update(['is_active' => false]);
                activity()
                    ->performedOn($product)
                    ->causedBy(auth()->user())
                    ->log('Producto desactivado (tiene ventas asociadas)');
                return ['type' => 'soft', 'message' => 'Producto desactivado'];
            } else {
                $product->delete();
                activity()
                    ->causedBy(auth()->user())
                    ->log("Producto {$product->primary_name} eliminado permanentemente");
                return ['type' => 'hard', 'message' => 'Producto eliminado'];
            }
        });
    }

    public function uploadImages(Product $product, array $images)
    {
        $uploadedImages = [];
        $currentImagesCount = $product->getMedia('images')->count();

        if ($currentImagesCount + count($images) > 5) {
            throw new ProductException('Máximo 5 imágenes por producto');
        }

        foreach ($images as $image) {
            $media = $product->addMedia($image)
                ->toMediaCollection('images');
            
            $uploadedImages[] = [
                'id' => $media->id,
                'name' => $media->file_name,
                'original_url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
                'medium_url' => $media->getUrl('medium'),
                'large_url' => $media->getUrl('large'),
                'size' => $this->formatBytes($media->size),
                'order' => $media->order_column,
            ];
        }

        // Marcar primera imagen como principal si no hay ninguna
        if ($currentImagesCount === 0 && count($uploadedImages) > 0) {
            $product->getMedia('images')->first()->setCustomProperty('is_main', true)->save();
        }

        return $uploadedImages;
    }

    public function reorderImages(Product $product, array $order)
    {
        $media = $product->getMedia('images');
        
        foreach ($order as $index => $mediaId) {
            $mediaItem = $media->firstWhere('id', $mediaId);
            if ($mediaItem) {
                $mediaItem->order_column = $index + 1;
                $mediaItem->save();
            }
        }

        return $product->getMedia('images');
    }

    public function deleteImage(Product $product, int $mediaId)
    {
        $media = $product->getMedia('images');
        
        if ($media->count() <= 1) {
            throw new ProductException('No se puede eliminar la única imagen del producto');
        }

        $mediaItem = $media->firstWhere('id', $mediaId);
        if ($mediaItem) {
            $mediaItem->delete();
            
            // Reordenar imágenes restantes
            $product->getMedia('images')->each(function ($item, $index) {
                $item->order_column = $index + 1;
                $item->save();
            });
        }

        return true;
    }

    public function toggleVisibility(Product $product, bool $visible)
    {
        if (!$product->is_active && $visible) {
            throw new ProductException('No se puede hacer visible un producto inactivo');
        }

        $product->update(['visible_online' => $visible]);

        event(new ProductVisibilityChanged($product, $visible));

        return $product;
    }

    private function generateUniqueSKU()
    {
        do {
            $sku = 'PROD-' . strtoupper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
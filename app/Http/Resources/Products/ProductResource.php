<?php
// app/Http/Resources/Products/ProductResource.php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // ✅ Obtener parámetros del request
        $warehouseId = $request->input('warehouse_id');
        $priceListId = $request->input('price_list_id'); // Si no se envía, usa RETAIL por defecto

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'primary_name' => $this->primary_name,
            'secondary_name' => $this->secondary_name,
            'description' => $this->description,

            'category' => $this->when($this->relationLoaded('category'), function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                    'level' => $this->category->level,
                    'parent_id' => $this->category->parent_id,
                    'normal_margin_percentage' => $this->category->getEffectiveNormalMargin(),
                    'min_margin_percentage' => $this->category->getEffectiveMinMargin(),
                    'is_active' => $this->category->is_active,
                ] : null;
            }),

            'brand' => $this->brand,
            'unit_measure' => $this->unit_measure,
            'tax_type' => $this->tax_type,
            'min_stock' => $this->min_stock,
            'weight' => $this->weight,
            'barcode' => $this->barcode,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'visible_online' => $this->visible_online,
            'is_new' => $this->is_new,

            // Atributos personalizados
            'attributes' => $this->whenLoaded('attributes', function () {
                return $this->attributes->map(function ($attr) {
                    return [
                        'id' => $attr->id,
                        'name' => $attr->name,
                        'value' => $attr->value,
                    ];
                });
            }),

            // ==================== COSTOS Y STOCK ====================
            'average_cost' => $this->average_cost,
            'total_stock' => $this->total_stock,
            'is_in_stock' => $this->isInStock(),

            // ==================== PRECIOS ====================
            // ✅ Precio principal (según lista y almacén solicitados)
            'sale_price' => $this->getSalePrice($priceListId, $warehouseId),
            'min_sale_price' => $this->getMinSalePrice($priceListId, $warehouseId),
            // Se puede calcular por el frontend y es mejor para no causar respuestas lentas
            // 'profit_margin' => $this->getProfitMargin($priceListId, $warehouseId),
            'has_price' => $this->hasPrice($priceListId, $warehouseId),

            // ✅ Precio más bajo disponible (útil para mostrar ofertas)
            'best_price' => $this->when(
                $request->input('include_best_price'),
                fn() => $this->getBestPrice($warehouseId)
            ),

            // ✅ Todos los precios del producto (todas las listas)
            'all_prices' => $this->when(
                true,
                fn() => $this->getAllPrices($warehouseId)
            ),

            // ✅ Precios por almacén (para gestión de inventario)
            'warehouse_prices' => $this->when(
                $request->input('include_warehouses'),
                fn() => $this->getPricesByWarehouse($priceListId)
            ),

            // ==================== LOTES E INVENTARIO ====================
            'active_batches' => $this->when(
                $request->input('include_batches'),
                fn() => $this->getActiveBatchesFormatted()
            ),

            'inventory' => $this->when(
                $request->input('include_inventory'),
                fn() => $this->getInventoryFormatted()
            ),

            // ==================== IMÁGENES ====================
            'images' => $this->getImagesFormatted(),
            'primary_image' => $this->getPrimaryImage(),

            // ==================== FECHAS ====================
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    /**
     * Obtener inventario formateado por almacén
     */
    private function getInventoryFormatted(): array
    {
        return $this->inventory()
            ->with('warehouse:id,name,is_main')
            ->get()
            ->map(function ($inv) {
                return [
                    'warehouse_id' => $inv->warehouse_id,
                    'warehouse_name' => $inv->warehouse->name,
                    'is_main_warehouse' => $inv->warehouse->is_main,
                    'available_stock' => $inv->available_stock,
                    'reserved_stock' => $inv->reserved_stock,
                    'average_cost' => $inv->average_cost,
                ];
            })
            ->toArray();
    }

    /**
     * Obtener lotes activos formateados
     */
    private function getActiveBatchesFormatted(): array
    {
        return $this->purchaseBatches()
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->with('warehouse:id,name')
            ->get()
            ->map(fn($batch) => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'warehouse_id' => $batch->warehouse_id,
                'warehouse_name' => $batch->warehouse->name ?? null,
                'quantity_available' => $batch->quantity_available,
                'purchase_price' => $batch->purchase_price,
                'purchase_date' => $batch->purchase_date?->format('Y-m-d'),
            ])
            ->toArray();
    }

    /**
     * Obtener imagen principal
     */
    private function getPrimaryImage(): ?array
    {
        $primaryMedia = $this->getMedia('images')
            ->firstWhere(fn($media) => $media->getCustomProperty('is_primary', false) === true);

        if (!$primaryMedia) {
            $primaryMedia = $this->getMedia('images')->first();
        }

        if (!$primaryMedia) {
            return null;
        }

        return [
            'id' => $primaryMedia->id,
            'original_url' => $primaryMedia->getUrl(),
            'thumb_url' => $primaryMedia->getUrl('thumb'),
            'medium_url' => $primaryMedia->getUrl('medium'),
            'large_url' => $primaryMedia->getUrl('large'),
        ];
    }

    /**
     * Obtener todas las imágenes formateadas
     */
    private function getImagesFormatted(): array
    {
        return $this->getMedia('images')
            ->sortBy(fn($media) => $media->getCustomProperty('order', 999))
            ->map(function ($media) {
                return [
                    'id' => $media->id,
                    'name' => $media->file_name,
                    'original_url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb'),
                    'medium_url' => $media->getUrl('medium'),
                    'large_url' => $media->getUrl('large'),
                    'size' => $this->formatBytes($media->size),
                    'order' => $media->getCustomProperty('order', 0),
                    'is_primary' => $media->getCustomProperty('is_primary', false),
                ];
            })
            ->values()
            ->toArray();
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

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

            // ✅ SOLO LISTADO (y Show)
            'sale_price' => $this->getSalePrice($priceListId, $warehouseId),
            'has_promotion' => $this->has_promotion,
            'total_stock' => $this->total_stock,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,

            // ✅ CATEGORÍA (Optimizado para lista)
            'category' => $this->whenLoaded('category', function () use ($request) {
                // Versión simple para lista
                $data = [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];

                // Versión completa para Show
                if ($request->routeIs('*.show')) {
                    $data['slug'] = $this->category->slug;
                    $data['level'] = $this->category->level;
                    $data['parent_id'] = $this->category->parent_id;
                    $data['parent'] = $this->category->parent ? [
                        'id' => $this->category->parent->id,
                        'name' => $this->category->parent->name
                    ] : null;
                    $data['normal_margin_percentage'] = $this->category->getEffectiveNormalMargin();
                    $data['min_margin_percentage'] = $this->category->getEffectiveMinMargin();
                    $data['is_active'] = $this->category->is_active;
                }

                return $data;
            }),

            // ✅ IMÁGENES (Optimizado: Solo thumbs para lista)
            'images' => $this->when(true, function () use ($request) {
                // En Show devolvemos todo completo
                if ($request->routeIs('*.show')) {
                    return $this->getImagesFormatted();
                }

                // En Lista devolvemos array ligero pero con TODAS las opciones para que el frontend elija
                return $this->getMedia('images')
                    ->sortBy(fn($media) => $media->getCustomProperty('order', 999))
                    ->map(function ($media) {
                        return [
                            'id' => $media->id,
                            'thumb_url' => $media->getUrl('thumb'),
                            'medium_url' => $media->getUrl('medium'),
                            'large_url' => $media->getUrl('large'),
                            'original_url' => $media->getUrl(),
                            'is_primary' => $media->getCustomProperty('is_primary', false),
                        ];
                    })
                    ->values()
                    ->toArray();
            }),

            // =================================================================================
            // ❌ CAMPOS SOLO PARA SHOW (Ocultos en lista)
            // =================================================================================

            'secondary_name' => $this->when($request->routeIs('*.show'), $this->secondary_name),
            'brand' => $this->when($request->routeIs('*.show'), $this->brand),
            'description' => $this->when($request->routeIs('*.show'), $this->description),
            'unit_measure' => $this->when($request->routeIs('*.show'), $this->unit_measure),
            'tax_type' => $this->when($request->routeIs('*.show'), $this->tax_type),
            'weight' => $this->when($request->routeIs('*.show'), $this->weight),
            'barcode' => $this->when($request->routeIs('*.show'), $this->barcode),
            'visible_online' => $this->when($request->routeIs('*.show'), $this->visible_online),
            'is_new' => $this->when($request->routeIs('*.show'), $this->is_new),

            // Costos
            'average_cost' => $this->when($request->routeIs('*.show'), $this->average_cost),
            'initial_cost' => $this->when($request->routeIs('*.show'), $this->initial_cost),

            // Precios adicionales
            'min_sale_price' => $this->when($request->routeIs('*.show'), fn() => $this->getMinSalePrice($priceListId, $warehouseId)),
            'has_price' => $this->when($request->routeIs('*.show'), fn() => $this->hasPrice($priceListId, $warehouseId)),

            'attributes' => $this->whenLoaded('attributes', function () use ($request) {
                return $request->routeIs('*.show') ? $this->attributes->map(function ($attr) {
                    return [
                        'id' => $attr->id,
                        'name' => $attr->name,
                        'value' => $attr->value,
                    ];
                }) : null;
            }),

            // Precios raw para edición
            'prices' => $this->when(
                $request->routeIs('*.show') || $request->input('include_raw_prices'),
                fn() => $this->productPrices->map(function ($price) {
                    return [
                        'id' => $price->id,
                        'price_list_id' => $price->price_list_id,
                        'warehouse_id' => $price->warehouse_id,
                        'price' => (float) $price->price,
                        'min_price' => $price->min_price ? (float) $price->min_price : null,
                        'currency' => $price->currency,
                        'min_quantity' => $price->min_quantity,
                        'is_active' => $price->is_active,
                    ];
                })
            ),

            'all_prices' => $this->when(
                $request->routeIs('*.show') || $request->input('include_all_prices'),
                fn() => $this->getAllPrices($warehouseId)
            ),

            'active_batches' => $this->when(
                $request->routeIs('*.show') || $request->input('include_batches'),
                fn() => $this->getActiveBatchesFormatted()
            ),

            'inventory' => $this->when(
                $request->routeIs('*.show') || $request->input('include_inventory'),
                fn() => $this->getInventoryFormatted()
            ),

            'created_at' => $this->when($request->routeIs('*.show'), $this->created_at),
            'updated_at' => $this->when($request->routeIs('*.show'), $this->updated_at),
            'deleted_at' => $this->when($request->routeIs('*.show'), $this->deleted_at),
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

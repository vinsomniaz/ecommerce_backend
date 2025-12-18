<?php

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $warehouseId = $request->get('warehouse_id');
        $priceListId = $request->get('price_list_id'); // Opcional
        
        // Obtener inventario del almacén seleccionado
        $inventory = $this->inventory->firstWhere('warehouse_id', $warehouseId);
        
        return [
            // ==================== INFORMACIÓN BÁSICA ====================
            'id' => $this->id,
            'sku' => $this->sku,
            'primary_name' => $this->primary_name,
            'secondary_name' => $this->secondary_name,
            'description' => $this->description,
            'brand' => $this->brand,
            'unit_measure' => $this->unit_measure,
            'barcode' => $this->barcode,
            
            // ==================== CATEGORÍA ====================
            'category' => $this->when($this->relationLoaded('category'), function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                    'level' => $this->category->level,
                    'parent_id' => $this->category->parent_id,
                    'parent_name' => $this->category->parent?->name,
                    'normal_margin_percentage' => $this->category->getEffectiveNormalMargin(),
                    'min_margin_percentage' => $this->category->getEffectiveMinMargin(),
                ] : null;
            }),
            
            // ==================== COSTOS Y STOCK GLOBAL ====================
            'average_cost' => (float) $this->average_cost,
            'initial_cost' => (float) $this->initial_cost,
            'distribution_price' => (float) $this->distribution_price,
            'total_stock' => $this->total_stock,
            'min_stock' => $this->min_stock,
            'is_in_stock' => $this->isInStock(),
            
            // ==================== INVENTARIO DEL ALMACÉN SELECCIONADO ====================
            'warehouse_inventory' => [
                'warehouse_id' => $warehouseId,
                'available_stock' => $inventory?->available_stock ?? 0,
                'reserved_stock' => $inventory?->reserved_stock ?? 0,
                'in_stock' => ($inventory?->available_stock ?? 0) > 0,
                'average_cost' => (float) ($inventory?->average_cost ?? $this->distribution_price),
                'last_movement_at' => $inventory?->last_movement_at,
            ],
            
            // ==================== PRECIOS ====================
            'pricing' => [
                // Precio de venta según lista de precios
                'sale_price' => $this->getSalePrice($priceListId, $warehouseId),
                'min_sale_price' => $this->getMinSalePrice($priceListId, $warehouseId),
                'has_price' => $this->hasPrice($priceListId, $warehouseId),
                
                // Precios sugeridos para cotización
                'suggested_price' => $this->getSuggestedPrice($inventory),
                'min_allowed_price' => $this->getMinAllowedPrice($inventory),
                
                // Todos los precios disponibles
                'all_prices' => $this->getallPrices($warehouseId),
            ],
            
            // ==================== MÁRGENES ====================
            'margins' => [
                'min_margin' => (float) ($this->category?->getEffectiveMinMargin() ?? 10),
                'normal_margin' => (float) ($this->category?->getEffectiveNormalMargin() ?? 20),
            ],
            
            // ==================== PROVEEDORES ====================
            'suppliers_count' => $this->whenLoaded('supplierProducts', function () {
                return $this->supplierProducts->count();
            }, 0),
            
            'suppliers' => $this->when(
                $this->relationLoaded('supplierProducts'),
                fn() => $this->supplierProducts->map(fn($sp) => [
                    'id' => $sp->id,
                    'supplier_id' => $sp->supplier_id,
                    'supplier_name' => $sp->supplier?->display_name,
                    'supplier_sku' => $sp->supplier_sku,
                    'purchase_price' => (float) $sp->purchase_price,
                    'distribution_price' => (float) $sp->distribution_price,
                    'available_stock' => $sp->available_stock,
                    'delivery_days' => $sp->delivery_days,
                    'priority' => $sp->priority,
                ])
            ),
            
            // ==================== IMÁGENES ====================
            'images' => $this->getImagesFormatted(),
            'primary_image' => $this->getPrimaryImage(),
            
            // ==================== ATRIBUTOS ====================
            'attributes' => $this->whenLoaded('attributes', function () {
                return $this->attributes->map(function ($attr) {
                    return [
                        'id' => $attr->id,
                        'name' => $attr->name,
                        'value' => $attr->value,
                    ];
                });
            }),
            
            // ==================== ESTADO ====================
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'visible_online' => (bool) $this->visible_online,
            'is_new' => (bool) $this->is_new,
            
            // ==================== FECHAS ====================
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    /**
     * Calcular precio sugerido basado en margen normal de la categoría
     */
    private function getSuggestedPrice($inventory): float
    {
        $cost = (float) ($inventory?->average_cost ?? $this->distribution_price);
        $normalMargin = $this->category?->getEffectiveNormalMargin() ?? 20;
        
        return round($cost / (1 - ($normalMargin / 100)), 2);
    }
    
    /**
     * Calcular precio mínimo permitido basado en margen mínimo de la categoría
     */
    private function getMinAllowedPrice($inventory): float
    {
        $cost = (float) ($inventory?->average_cost ?? $this->distribution_price);
        $minMargin = $this->category?->getEffectiveMinMargin() ?? 10;
        
        return round($cost / (1 - ($minMargin / 100)), 2);
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
                    'thumb_url' => $media->getUrl('thumb'),
                    'medium_url' => $media->getUrl('medium'),
                    'is_primary' => $media->getCustomProperty('is_primary', false),
                ];
            })
            ->values()
            ->toArray();
    }
}

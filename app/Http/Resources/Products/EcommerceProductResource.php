<?php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Obtener parámetros del request que puedan afectar la visualización de precios
        $warehouseId = $request->input('warehouse_id');
        $priceListId = $request->input('price_list_id');
        // El factor de cambio ya debería estar inyectado en el request por el controlador si aplica
        $exchangeRateFactor = $request->get('exchange_rate_factor');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'primary_name' => $this->primary_name,
            'secondary_name' => $this->secondary_name,
            'description' => $this->description,
            'slug' => $this->slug, // Aseguramos que el slug esté disponible si existe en el modelo

            'category' => $this->when($this->relationLoaded('category'), function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'level' => $this->category->level,
                    'parent_id' => $this->category->parent_id,
                ] : null;
            }),

            'brand' => $this->brand,
            'unit_measure' => $this->unit_measure,
            'tax_type' => $this->tax_type,
            'weight' => $this->weight, //preguntar si eliminar
            'barcode' => $this->barcode,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'visible_online' => $this->visible_online,
            'is_new' => $this->is_new,

            // Atributos personalizados (Públicos)
            'attributes' => $this->whenLoaded('attributes', function () {
                return $this->attributes->map(function ($attr) {
                    return [
                        'id' => $attr->id,
                        'name' => $attr->name,
                        'value' => $attr->value,
                    ];
                });
            }),

            // ==================== DISPONIBILIDAD ====================
            // Solo indicamos si hay stock o no, sin dar detalles de almacenes internos a menos que sea necesario
            'is_in_stock' => $this->isInStock(),
            // 'total_stock' => $this->total_stock, // Ocultar stock total real si es estrategia comercial

            // ==================== PRECIOS PÚBLICOS ====================
            'sale_price' => $this->getSalePrice($priceListId, $warehouseId, $exchangeRateFactor),

            // Si hay un precio de oferta o cálculo de "mejor precio" público
            'best_price' => $this->when(
                $request->input('include_best_price'),
                fn() => $this->getBestPrice($warehouseId)
            ),

            // Omitimos: initial_cost, min_sale_price, profit_margin, warehouse_prices, active_batches, inventory

            // ==================== IMÁGENES ====================
            'images' => $this->getImagesFormatted(),
            'primary_image' => $this->getPrimaryImage(),
        ];
    }

    /**
     * Reutilizamos la lógica de formateo de imágenes del resource original o la duplicamos simplificada.
     * Como son métodos privados en el otro resource, los duplicamos aquí para desacoplar.
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
                    'order' => $media->getCustomProperty('order', 0),
                    'is_primary' => $media->getCustomProperty('is_primary', false),
                ];
            })
            ->values()
            ->toArray();
    }
}

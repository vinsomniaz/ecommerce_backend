<?php
// app/Http/Resources/ProductResource.php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'primary_name' => $this->primary_name,
            'secondary_name' => $this->secondary_name,
            'description' => $this->description,
            'brand' => $this->brand,

            // Categoría
            'category' => [
                'id' => $this->category_id,
                // 'name' => $this->category?->name,
            ],

            // Precios
            'unit_price' => (float) $this->unit_price,
            'cost_price' => (float) $this->cost_price,
            'profit_margin' => $this->unit_price > 0
                ? round((($this->unit_price - $this->cost_price) / $this->unit_price) * 100, 2)
                : 0,

            // Stock
            'min_stock' => $this->min_stock,
            // 'available_stock' => $this->stock_available ?? 0,
            // 'total_stock' => $this->total_stock ?? 0,
            // 'is_low_stock' => ($this->stock_available ?? 0) <= $this->min_stock,

            // Medidas y impuestos
            'unit_measure' => $this->unit_measure,
            'tax_type' => $this->tax_type,
            'weight' => $this->weight ? (float) $this->weight : null,

            // Estados
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'visible_online' => (bool) $this->visible_online,

            // Imágenes
            'images' => $this->getMedia('images')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumb' => $media->getUrl('thumb'),
                    'medium' => $media->getUrl('medium'),
                    'large' => $media->getUrl('large'),
                    'file_name' => $media->file_name,
                    'size' => $media->size,
                ];
            }),
            'images_count' => $this->getMedia('images')->count(),

            // Fechas
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}

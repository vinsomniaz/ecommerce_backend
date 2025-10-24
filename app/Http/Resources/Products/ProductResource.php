<?php
// app/Http/Resources/Products/ProductResource.php

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
            'category_id' => $this->category_id,
            'brand' => $this->brand,
            'unit_measure' => $this->unit_measure,
            'unit_price' => $this->unit_price,
            'cost_price' => $this->cost_price,
            'tax_type' => $this->tax_type,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'visible_online' => $this->visible_online,

            // Imágenes con todas las conversiones
            'images' => $this->getMedia('images')->map(function ($media) {
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
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
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

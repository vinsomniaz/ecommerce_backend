<?php

namespace App\Http\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'primary_name' => $this->primary_name,
            'unit_price' => (float) $this->unit_price,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'visible_online' => $this->visible_online,
            'stock_available' => $this->stock_available,
            'images_count' => $this->images_count,
            'category' => [
                'id' => $this->category->id ?? null,
                'name' => $this->category->name ?? null,
            ],
            'main_image' => $this->getMedia('images')->first()?->getUrl('thumb'),
        ];
    }
}
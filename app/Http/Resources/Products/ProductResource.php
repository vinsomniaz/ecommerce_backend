<?php

namespace App\Http\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'primary_name' => $this->primary_name,
            'secondary_name' => $this->secondary_name,
            'description' => $this->description,
            'brand' => $this->brand,
            'unit_price' => (float) $this->unit_price,
            'cost_price' => (float) $this->cost_price,
            'min_stock' => $this->min_stock,
            'unit_measure' => $this->unit_measure,
            'tax_type' => $this->tax_type,
            'weight' => (float) $this->weight,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'visible_online' => $this->visible_online,
            'stock_available' => $this->stock_available,
            'images_count' => $this->images_count,
            // 'category' => new CategoryResource($this->whenLoaded('category')),
            // 'images' => $this->when($this->relationLoaded('media'), function () {
            //     return $this->getMedia('images')->map(function ($media) {
            //         return [
            //             'id' => $media->id,
            //             'name' => $media->file_name,
            //             'url' => $media->getUrl(),
            //             'thumb_url' => $media->getUrl('thumb'),
            //             'medium_url' => $media->getUrl('medium'),
            //             'large_url' => $media->getUrl('large'),
            //             'order' => $media->order_column,
            //             'is_main' => $media->getCustomProperty('is_main', false),
            //         ];
            //     });
            // }),
            // 'attributes' => ProductAttributeResource::collection($this->whenLoaded('attributes')),
            // 'inventory' => InventoryResource::collection($this->whenLoaded('inventory')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
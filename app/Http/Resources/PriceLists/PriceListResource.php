<?php
// app/Http/Resources/PriceLists/PriceListResource.php

namespace App\Http\Resources\PriceLists;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,

            // Contadores
            'products_count' => $this->when(
                $this->relationLoaded('productPrices') || isset($this->product_prices_count),
                fn() => $this->product_prices_count ?? $this->productPrices->count()
            ),

            // Fechas
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Información adicional
            'status_text' => $this->is_active ? 'Activa' : 'Inactiva',
            'status_color' => $this->is_active ? 'success' : 'error',
        ];
    }
}

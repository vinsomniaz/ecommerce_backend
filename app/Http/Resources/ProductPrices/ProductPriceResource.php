<?php
// app/Http/Resources/ProductPrices/ProductPriceResource.php

namespace App\Http\Resources\ProductPrices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Relaciones
            'product' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'primary_name' => $this->product->primary_name,
                    'brand' => $this->product->brand,
                    'average_cost' => $this->product->average_cost,
                ];
            }),

            'price_list' => $this->when($this->relationLoaded('priceList'), function () {
                return [
                    'id' => $this->priceList->id,
                    'code' => $this->priceList->code,
                    'name' => $this->priceList->name,
                    'is_default' => $this->priceList->is_default ?? false,
                ];
            }),

            'warehouse' => $this->when($this->relationLoaded('warehouse'), function () {
                return $this->warehouse ? [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'is_main' => $this->warehouse->is_main,
                ] : null;
            }),

            // Precios
            'price' => (float) $this->price,
            'min_price' => $this->min_price ? (float) $this->min_price : null,
            'currency' => $this->currency,
            'min_quantity' => $this->min_quantity,

            // Información de alcance
            'is_warehouse_specific' => $this->warehouse_id !== null,
            'warehouse_id' => $this->warehouse_id,

            // Margen calculado (si hay producto cargado)
            'profit_margin' => $this->when(
                $this->relationLoaded('product') && $this->product->average_cost > 0,
                function () {
                    $cost = $this->product->average_cost;
                    return round((($this->price - $cost) / $cost) * 100, 2);
                }
            ),

            'profit_amount' => $this->when(
                $this->relationLoaded('product') && $this->product->average_cost > 0,
                function () {
                    return round($this->price - $this->product->average_cost, 2);
                }
            ),

            // Estado
            'is_active' => $this->is_active,

            // Fechas de auditoría
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

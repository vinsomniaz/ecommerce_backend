<?php
namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // ✅ Obtener precio según lista de precios (puede venir del request)
        $priceListId = $request->input('price_list_id');

        return [
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,

            // Información del producto
            'product' => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'primary_name' => $this->product->primary_name,
                'secondary_name' => $this->product->secondary_name,
                'brand' => $this->product->brand,
                'barcode' => $this->product->barcode,
                'is_active' => $this->product->is_active,
                'min_stock' => $this->product->min_stock,
                'category' => $this->when($this->relationLoaded('product'), function () {
                    return [
                        'id' => $this->product->category->id ?? null,
                        'name' => $this->product->category->name ?? null,
                        'normal_margin_percentage' => $this->product->category->getEffectiveNormalMargin() ?? null,
                    ];
                }),
                'images' => $this->when($this->product->relationLoaded('media'), function () {
                    return $this->product->getMedia('images')->map(function ($media) {
                        return [
                            'id' => $media->id,
                            'url' => $media->getUrl(),
                            'thumb_url' => $media->getUrl('thumb'),
                            'medium_url' => $media->getUrl('medium'),
                        ];
                    });
                }),
            ],

            // Información del almacén
            'warehouse' => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'address' => $this->warehouse->address ?? null,
                'is_main' => $this->warehouse->is_main ?? false,
                'is_active' => $this->warehouse->is_active ?? true,
            ],

            // Stock
            'available_stock' => $this->available_stock,
            'reserved_stock' => $this->reserved_stock,
            'total_stock' => $this->available_stock + $this->reserved_stock,

            // Estado del stock
            'stock_status' => $this->getStockStatus(),
            'is_low_stock' => $this->available_stock <= ($this->product->min_stock ?? 0),
            'is_out_of_stock' => $this->available_stock === 0,

            // ✅ ACTUALIZADO: Precios desde product_prices
            'sale_price' => $this->getSalePrice($priceListId),
            'min_sale_price' => $this->getMinSalePrice($priceListId),
            'profit_margin' => $this->getProfitMargin($priceListId),
            'average_cost' => $this->average_cost ? number_format($this->average_cost, 4, '.', '') : null,

            // ✅ NUEVO: Información de precios disponibles
            'available_prices' => $this->when(
                $request->input('include_all_prices'),
                fn() => $this->getAllPrices()
            ),

            // Fechas
            'price_updated_at' => $this->price_updated_at?->toIso8601String(),
            'last_movement_at' => $this->last_movement_at?->toIso8601String(),

            // Metadatos útiles
            'stock_percentage' => $this->getStockPercentage(),
            'estimated_value' => $this->getEstimatedValue($priceListId),
        ];
    }

    /**
     * Obtener el estado del stock
     */
    private function getStockStatus(): string
    {
        if ($this->available_stock === 0) {
            return 'sin_stock';
        }

        if ($this->available_stock <= ($this->product->min_stock ?? 0)) {
            return 'stock_bajo';
        }

        return 'stock_normal';
    }

    /**
     * Calcular porcentaje de stock basado en el mínimo
     */
    private function getStockPercentage(): ?int
    {
        $minStock = $this->product->min_stock ?? 0;

        if ($minStock === 0) {
            return null;
        }

        $percentage = ($this->available_stock / $minStock) * 100;
        return min(100, (int) round($percentage));
    }

    /**
     * ✅ ACTUALIZADO: Calcular valor estimado con precio de lista
     */
    private function getEstimatedValue(?int $priceListId = null): ?float
    {
        $salePrice = $this->getSalePrice($priceListId);

        if (!$salePrice || $this->available_stock === 0) {
            return null;
        }

        return round($this->available_stock * $salePrice, 2);
    }
}

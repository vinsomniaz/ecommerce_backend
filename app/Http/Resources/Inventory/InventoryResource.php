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
                'code' => $this->warehouse->code ?? null,
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

            // Precios
            'sale_price' => $this->sale_price ? number_format($this->sale_price, 2, '.', '') : null,
            'profit_margin' => $this->profit_margin ? number_format($this->profit_margin, 2, '.', '') : null,
            'min_sale_price' => $this->min_sale_price ? number_format($this->min_sale_price, 2, '.', '') : null,

            // Fechas
            'price_updated_at' => $this->price_updated_at?->toIso8601String(),
            'last_movement_at' => $this->last_movement_at?->toIso8601String(),

            // Metadatos útiles
            'stock_percentage' => $this->getStockPercentage(),
            'estimated_value' => $this->getEstimatedValue(),
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
     * Calcular valor estimado del inventario
     */
    private function getEstimatedValue(): ?float
    {
        if (!$this->sale_price || $this->available_stock === 0) {
            return null;
        }

        return round($this->available_stock * $this->sale_price, 2);
    }
}

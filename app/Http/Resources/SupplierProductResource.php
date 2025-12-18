<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'supplier_id' => $this->supplier_id,

            // Información del proveedor
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'business_name' => $this->supplier->business_name,
                    'trade_name' => $this->supplier->trade_name,
                    'document_type' => $this->supplier->tipo_documento,
                    'document_number' => $this->supplier->numero_documento,
                    'phone' => $this->supplier->phone,
                    'email' => $this->supplier->email,
                ];
            }),

            // Información del producto (si está vinculado)
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'barcode' => $this->product->barcode,
                ];
            }),

            // Datos del scraper
            'supplier_sku' => $this->supplier_sku,
            'supplier_name' => $this->supplier_name,
            'brand' => $this->brand,
            'location' => $this->location,
            'source_url' => $this->source_url,
            'image_url' => $this->image_url,

            // Categorías
            'supplier_category' => $this->supplier_category,
            'category_suggested' => $this->category_suggested,

            // Precios y disponibilidad
            'purchase_price' => $this->purchase_price ? (float) $this->purchase_price : null,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'currency' => $this->currency,
            'available_stock' => (int) $this->available_stock,
            'stock_text' => $this->stock_text,
            'is_available' => (bool) $this->is_available,

            // Tracking
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'last_import_id' => $this->last_import_id,

            // Configuración
            'delivery_days' => $this->delivery_days,
            'min_order_quantity' => (int) $this->min_order_quantity,
            'priority' => (int) $this->priority,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,

            // Timestamps
            'price_updated_at' => $this->price_updated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

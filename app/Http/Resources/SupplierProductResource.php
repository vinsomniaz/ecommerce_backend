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
            'supplier' => [
                'id' => $this->supplier->id,
                'business_name' => $this->supplier->business_name,
                'document_type' => $this->supplier->tipo_documento,
                'document_number' => $this->supplier->numero_documento,
                'phone' => $this->supplier->phone,
                'email' => $this->supplier->email,
            ],
            
            // Precios y disponibilidad
            'supplier_sku' => $this->supplier_sku,
            'supplier_price' => (float) $this->supplier_price,
            'min_quantity' => (int) $this->min_quantity,
            'lead_time_days' => (int) $this->lead_time_days,
            'is_available' => (bool) $this->is_available,
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            
            // Información adicional
            'notes' => $this->notes,
            'last_purchase_date' => $this->last_purchase_date,
            'last_purchase_price' => $this->last_purchase_price ? (float) $this->last_purchase_price : null,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
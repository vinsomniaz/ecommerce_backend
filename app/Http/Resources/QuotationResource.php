<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quotation_code' => $this->quotation_code,
            'quotation_date' => $this->quotation_date,
            'valid_until' => $this->valid_until,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            
            // Cliente
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer_name,
                'document' => $this->customer_document,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
            ],
            
            // Vendedor
            'seller' => [
                'id' => $this->user_id,
                'name' => $this->user?->first_name . ' ' . $this->user?->last_name,
            ],
            
            // Warehouse
            'warehouse' => [
                'id' => $this->warehouse_id,
                'name' => $this->warehouse?->name,
            ],
            
            // Montos
            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'shipping_cost' => (float) $this->shipping_cost,
            'packaging_cost' => (float) $this->packaging_cost,
            'assembly_cost' => (float) $this->assembly_cost,
            'total' => (float) $this->total,
            
            // Márgenes
            'total_margin' => (float) $this->total_margin,
            'margin_percentage' => (float) $this->margin_percentage,
            
            // Comisiones
            'commission_percentage' => (float) $this->commission_percentage,
            'commission_amount' => (float) $this->commission_amount,
            'commission_paid' => (bool) $this->commission_paid,
            
            // Items
            'items' => QuotationDetailResource::collection($this->whenLoaded('details')),
            
            // PDF
            'pdf_url' => $this->pdf_path ? url($this->pdf_path) : null,
            'sent_at' => $this->sent_at,
            'sent_to_email' => $this->sent_to_email,
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    private function getStatusLabel(): string
    {
        return match($this->status) {
            'draft' => 'Borrador',
            'sent' => 'Enviada',
            'accepted' => 'Aceptada',
            'rejected' => 'Rechazada',
            'expired' => 'Vencida',
            'converted' => 'Convertida a venta',
            default => 'Desconocido',
        };
    }
}

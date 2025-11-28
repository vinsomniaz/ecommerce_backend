<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationDetailResource extends JsonResource
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
            'quotation_id' => $this->quotation_id,
            
            // Información del producto
            'product' => [
                'id' => $this->product_id,
                'name' => $this->product_name,
                'sku' => $this->product_sku,
                'brand' => $this->product_brand,
            ],
            
            // Cantidad y precios
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount' => (float) ($this->discount ?? 0),
            'discount_percentage' => (float) ($this->discount_percentage ?? 0),
            
            // Subtotales e impuestos
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            
            // Costos y márgenes
            'purchase_price' => (float) ($this->purchase_price ?? 0),
            'distribution_price' => (float) ($this->distribution_price ?? 0),
            'unit_cost' => (float) ($this->unit_cost ?? 0),
            'total_cost' => (float) ($this->total_cost ?? 0),
            'unit_margin' => (float) ($this->unit_margin ?? 0),
            'total_margin' => (float) ($this->total_margin ?? 0),
            'margin_percentage' => (float) ($this->margin_percentage ?? 0),
            
            // Origen del producto
            'source_type' => $this->source_type,
            'source_label' => $this->getSourceLabel(),
            'is_from_warehouse' => $this->is_from_warehouse,
            'is_from_supplier' => $this->is_from_supplier,
            
            // Warehouse (si aplica)
            'warehouse' => $this->when($this->warehouse_id, function () {
                return [
                    'id' => $this->warehouse_id,
                    'name' => $this->warehouse?->name,
                ];
            }),
            
            // Proveedor (si aplica)
            'supplier' => $this->when($this->supplier_id, function () {
                return [
                    'id' => $this->supplier_id,
                    'business_name' => $this->supplier?->business_name,
                    'document_number' => $this->supplier?->numero_documento,
                ];
            }),
            
            // Producto del proveedor (si aplica)
            'supplier_product' => $this->when($this->supplier_product_id, function () {
                return [
                    'id' => $this->supplier_product_id,
                    'supplier_sku' => $this->supplierProduct?->supplier_sku,
                    'supplier_price' => (float) ($this->supplierProduct?->supplier_price ?? 0),
                    'lead_time_days' => (int) ($this->supplierProduct?->lead_time_days ?? 0),
                ];
            }),
            
            // Proveedor sugerido (si aplica)
            'suggested_supplier' => $this->when($this->suggested_supplier_id, function () {
                return [
                    'id' => $this->suggested_supplier_id,
                    'business_name' => $this->suggestedSupplier?->business_name,
                    'price' => (float) ($this->supplier_price ?? 0),
                ];
            }),
            
            // Estado de stock
            'stock_info' => [
                'in_stock' => (bool) $this->in_stock,
                'available_stock' => (int) ($this->available_stock ?? 0),
                'is_requested_from_supplier' => (bool) $this->is_requested_from_supplier,
                'status_label' => $this->getStockStatusLabel(),
            ],
            
            // Notas
            'notes' => $this->notes,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
    
    /**
     * Get source type label
     */
    private function getSourceLabel(): string
    {
        return match($this->source_type) {
            'warehouse' => 'Almacén',
            'supplier' => 'Proveedor',
            default => 'Desconocido',
        };
    }
    
    /**
     * Get stock status label
     */
    private function getStockStatusLabel(): string
    {
        if ($this->source_type === 'supplier') {
            return $this->is_requested_from_supplier 
                ? 'Solicitado a proveedor' 
                : 'Por solicitar';
        }
        
        if ($this->in_stock) {
            return 'En stock (' . ($this->available_stock ?? 0) . ' disponibles)';
        }
        
        return 'Sin stock';
    }
}
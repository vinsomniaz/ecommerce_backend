<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => [
                'id' => $this->supplier->id,
                'business_name' => $this->supplier->business_name,
                'trade_name' => $this->supplier->trade_name,
                'document_number' => $this->supplier->numero_documento,
                'estado_sunat' => $this->supplier->estado_sunat,
                'condicion_sunat' => $this->supplier->condicion_sunat,
            ],

            // Metadata del scraping
            'fetched_at' => $this->fetched_at?->toISOString(),
            'margin_percent' => $this->margin_percent ? (float) $this->margin_percent : null,
            'source_totals' => $this->source_totals,
            'items_count' => (int) $this->items_count,

            // Estado y procesamiento
            'status' => $this->status,
            'total_products' => (int) $this->total_products,
            'processed_products' => (int) $this->processed_products,
            'updated_products' => (int) $this->updated_products,
            'new_products' => (int) $this->new_products,
            'processed_at' => $this->processed_at?->toISOString(),
            'error_message' => $this->error_message,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Métricas calculadas
            'success_rate' => $this->total_products > 0
                ? round(($this->processed_products / $this->total_products) * 100, 2)
                : 0,
            'processing_time' => $this->processed_at && $this->created_at
                ? $this->created_at->diffInSeconds($this->processed_at)
                : null,
        ];
    }
}

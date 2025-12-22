<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierCategoryMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier_category' => $this->supplier_category,
            'category_id' => $this->category_id,
            'confidence' => $this->confidence ? (float) $this->confidence : null,
            'is_active' => (bool) $this->is_active,

            // Información del proveedor
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'business_name' => $this->supplier->business_name,
                    'trade_name' => $this->supplier->trade_name,
                    'document_type' => $this->supplier->tipo_documento,
                    'document_number' => $this->supplier->numero_documento,
                    'estado_sunat' => $this->supplier->estado_sunat,
                    'condicion_sunat' => $this->supplier->condicion_sunat,
                ];
            }),

            // Información de la categoría interna
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'code' => $this->category->code ?? null,
                ];
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

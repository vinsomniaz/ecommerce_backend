<?php

namespace App\Http\Resources\Warehouses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
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
            'name' => $this->name,
            'ubigeo' => $this->ubigeo,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_main' => $this->is_main,
            'is_active' => $this->is_active,
            'visible_online' => $this->visible_online,
            'picking_priority' => $this->picking_priority,
            'ubigeo_data' => $this->whenLoaded('ubigeoData', function() {
                return [
                    'ubigeo' => $this->ubigeoData->ubigeo,
                    'departamento' => $this->ubigeoData->departamento,
                    'provincia' => $this->ubigeoData->provincia,
                    'distrito' => $this->ubigeoData->distrito,
                    'codigo_sunat' => $this->ubigeoData->codigo_sunat ?? null,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}

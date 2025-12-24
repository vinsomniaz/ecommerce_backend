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
        // ✅ Campos base para listado (ligero)
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'ubigeo' => $this->ubigeo,
            'is_main' => $this->is_main,
            'is_active' => $this->is_active,
            'visible_online' => $this->visible_online,
        ];

        // ✅ Campos adicionales solo para Show (detalles completos)
        if ($request->routeIs('*.show')) {
            $data['picking_priority'] = $this->picking_priority;

            $data['ubigeo_data'] = $this->whenLoaded('ubigeoData', function () {
                return [
                    'ubigeo' => $this->ubigeoData->ubigeo,
                    'departamento' => $this->ubigeoData->departamento,
                    'provincia' => $this->ubigeoData->provincia,
                    'distrito' => $this->ubigeoData->distrito,
                    'codigo_sunat' => $this->ubigeoData->codigo_sunat ?? null,
                ];
            });

            $data['created_at'] = $this->created_at?->toIso8601String();
            $data['updated_at'] = $this->updated_at?->toIso8601String();
        }

        return $data;
    }
}

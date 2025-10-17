<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            
            // Document information
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'tipo_persona' => $this->tipo_persona,
            
            // Personal/Business information
            'business_name' => $this->business_name,
            'trade_name' => $this->trade_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,
            
            // Contact information
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'ubigeo' => $this->ubigeo,
            
            // SUNAT status
            'estado_sunat' => $this->estado_sunat,
            'condicion_sunat' => $this->condicion_sunat,
            
            // Metadata
            'is_active' => $this->is_active,
            'registered_at' => $this->registered_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relations (only if loaded)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'ubigeo_data' => $this->whenLoaded('ubigeoData', function () {
                return [
                    'ubigeo' => $this->ubigeoData->ubigeo,
                    'departamento' => $this->ubigeoData->departamento ?? null,
                    'provincia' => $this->ubigeoData->provincia ?? null,
                    'distrito' => $this->ubigeoData->distrito ?? null,
                ];
            }),
        ];
    }
}
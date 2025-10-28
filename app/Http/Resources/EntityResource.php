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
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'tipo_persona' => $this->tipo_persona,
            'full_name' => $this->full_name,
            'trade_name' => $this->when($this->tipo_persona === 'juridica', $this->trade_name),
            'email' => $this->email,
            'phone' => $this->phone,
            
            // NUEVO: País y Ubigeo de la dirección fiscal
            'country_code' => $this->country_code,
            'country_name' => $this->whenLoaded('country', $this->country->name ?? null),
            'ubigeo' => $this->ubigeo,
            'ubigeo_name' => $this->whenLoaded('ubigeoData', $this->ubigeoData->distrito ?? null),

            'is_active' => $this->is_active,
            'registered_at' => $this->registered_at?->format('Y-m-d H:i:s'),

            // Relationships
            'default_address' => $this->whenLoaded('defaultAddress', function () {
                if (!$this->defaultAddress) return null;
                return [
                    'id' => $this->defaultAddress->id,
                    'address' => $this->defaultAddress->address,
                    'country_code' => $this->defaultAddress->country_code,
                    'country_name' => $this->defaultAddress->country->name ?? null,
                    'distrito' => $this->defaultAddress->ubigeoData->distrito ?? null,
                    'provincia' => $this->defaultAddress->ubigeoData->provincia ?? null,
                    'departamento' => $this->defaultAddress->ubigeoData->departamento ?? null,
                ];
            }),
            'user_id' => $this->whenLoaded('user', $this->user_id),
        ];
    }
}
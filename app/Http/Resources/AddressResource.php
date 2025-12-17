<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
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
            'entity_id' => $this->entity_id,
            'address' => $this->address,

            'country_code' => $this->country_code, // NUEVO
            'country' => $this->whenLoaded('country', [ // NUEVO
                'code' => $this->country->code,
                'name' => $this->country->name,
            ]),

            'ubigeo' => $this->ubigeo,
            'departamento' => $this->whenLoaded('ubigeoData', $this->ubigeoData->departamento ?? null),
            'provincia' => $this->whenLoaded('ubigeoData', $this->ubigeoData->provincia ?? null),
            'distrito' => $this->whenLoaded('ubigeoData', $this->ubigeoData->distrito ?? null),
            'postcode' => $this->postcode,

            'reference' => $this->reference,
            'phone' => $this->phone,
            'label' => $this->label,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

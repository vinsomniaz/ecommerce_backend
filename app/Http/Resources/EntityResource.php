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
            'tipo_documento_name' => $this->whenLoaded('documentType', $this->documentType?->name),
            'numero_documento' => $this->numero_documento,
            'tipo_persona' => $this->tipo_persona,
            'full_name' => $this->full_name,
            'trade_name' => $this->when($this->tipo_persona === 'juridica', $this->trade_name),

            // Campos SUNAT (solo para suppliers)
            'estado_sunat' => $this->when($this->type === 'supplier', $this->estado_sunat),
            'condicion_sunat' => $this->when($this->type === 'supplier', $this->condicion_sunat),

            // NOTA: email, phone, address ahora vienen de primaryContact y primaryAddress
            // Se mantienen en el root para compatibilidad con frontend
            'email' => $this->primaryContact?->email,
            'phone' => $this->primaryAddress?->phone ?? $this->primaryContact?->phone,
            'address' => $this->primaryAddress?->address,

            // País y Ubigeo de la dirección principal
            'country_code' => $this->primaryAddress?->country_code,
            'country_name' => $this->whenLoaded('primaryAddress.country', $this->primaryAddress?->country?->name),
            'ubigeo' => $this->primaryAddress?->ubigeo,
            'ubigeo_name' => $this->whenLoaded('primaryAddress.ubigeoData', $this->primaryAddress?->ubigeoData?->distrito),

            'is_active' => $this->is_active,
            'registered_at' => $this->registered_at?->format('Y-m-d H:i:s'),

            // NUEVO: Arrays completos de addresses y contacts
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),

            // Mantener compatibilidad: primary_address y primary_contact
            'primary_address' => $this->whenLoaded('primaryAddress', function () {
                if (!$this->primaryAddress) return null;
                return [
                    'id' => $this->primaryAddress->id,
                    'address' => $this->primaryAddress->address,
                    'country_code' => $this->primaryAddress->country_code,
                    'country_name' => $this->primaryAddress->country?->name,
                    'ubigeo' => $this->primaryAddress->ubigeo,
                    'distrito' => $this->primaryAddress->ubigeoData?->distrito,
                    'provincia' => $this->primaryAddress->ubigeoData?->provincia,
                    'departamento' => $this->primaryAddress->ubigeoData?->departamento,
                    'phone' => $this->primaryAddress->phone,
                    'reference' => $this->primaryAddress->reference,
                    'label' => $this->primaryAddress->label,
                ];
            }),

            'primary_contact' => $this->whenLoaded('primaryContact', function () {
                if (!$this->primaryContact) return null;
                return [
                    'id' => $this->primaryContact->id,
                    'full_name' => $this->primaryContact->full_name,
                    'position' => $this->primaryContact->position,
                    'email' => $this->primaryContact->email,
                    'phone' => $this->primaryContact->phone,
                    'web_page' => $this->primaryContact->web_page,
                ];
            }),
            'user_id' => $this->whenLoaded('user', $this->user_id),

            // Información completa del tipo de documento (opcional, para casos detallados)
            'document_type' => $this->whenLoaded('documentType', function () {
                return $this->documentType ? [
                    'code' => $this->documentType->code,
                    'name' => $this->documentType->name,
                    'length' => $this->documentType->length,
                ] : null;
            }),
        ];
    }
}

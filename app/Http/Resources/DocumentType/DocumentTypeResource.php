<?php

namespace App\Http\Resources\DocumentType;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'length' => $this->length,
            'validation_pattern' => $this->validation_pattern,
            'is_active' => $this->is_active,
        ];
    }
}
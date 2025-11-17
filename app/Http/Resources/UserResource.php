<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'cellphone'  => $this->cellphone,
            'is_active'  => $this->is_active,

            'roles'      => $this->getRoleNames(),
            'addresses'  => AddressResource::collection($this->whenLoaded('addresses')),

            'created_at' => $this->created_at,
        ];
    }
}

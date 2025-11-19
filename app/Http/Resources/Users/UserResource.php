<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name, // Accessor del modelo
            'email' => $this->email,
            'cellphone' => $this->cellphone,
            'is_active' => $this->is_active,

            // Roles (Spatie)
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'role' => $this->whenLoaded('roles', function () {
                return $this->roles->first()?->name;
            }),

            // Permisos (opcional, solo si se necesita)
            'permissions' => $this->when(
                $request->input('include_permissions'),
                fn() => $this->getAllPermissions()->pluck('name')
            ),

            // Entity relacionada
            'entity' => $this->when(
                $this->relationLoaded('entity') && $this->entity,
                function () {
                    return [
                        'id' => $this->entity->id,
                        'type' => $this->entity->type,
                        'tipo_documento' => $this->entity->tipo_documento,
                        'numero_documento' => $this->entity->numero_documento,
                        'razon_social' => $this->entity->razon_social,
                    ];
                }
            ),

            // Warehouse (si aplica)
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->when(
                $this->relationLoaded('warehouse') && $this->warehouse,
                function () {
                    return [
                        'id' => $this->warehouse->id,
                        'name' => $this->warehouse->name,
                    ];
                }
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->when($this->deleted_at, fn() => $this->deleted_at->toISOString()),
        ];
    }
}

<?php

namespace App\Http\Resources\Roles;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class RoleResource extends JsonResource
{
    /**
     * System roles that cannot be deleted
     */
    private const SYSTEM_ROLES = ['super-admin', 'admin', 'vendor', 'customer'];

    /**
     * Default colors for system roles (fallback)
     */
    private const DEFAULT_COLORS = [
        'super-admin' => '#7C3AED',
        'admin' => '#2563EB',
        'vendor' => '#059669',
        'customer' => '#F59E0B',
    ];

    /**
     * Default descriptions for system roles (fallback)
     */
    private const DEFAULT_DESCRIPTIONS = [
        'super-admin' => 'Acceso completo al sistema',
        'admin' => 'Gestión de ventas y cotizaciones, aprobación de operaciones',
        'vendor' => 'Creación de cotizaciones y ventas',
        'customer' => 'Acceso a compras y perfil personal',
    ];

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $isSystemRole = in_array($this->name, self::SYSTEM_ROLES);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->getDisplayName(),
            'color_hex' => $this->color_hex ?? (self::DEFAULT_COLORS[$this->name] ?? '#6B7280'),
            'description' => $this->description ?? (self::DEFAULT_DESCRIPTIONS[$this->name] ?? 'Rol personalizado'),
            'guard_name' => $this->guard_name,
            'is_system_role' => $isSystemRole,

            // Counts
            'permissions_count' => $this->whenCounted('permissions', $this->permissions_count ?? 0),
            'users_count' => User::whereHas('roles', fn($q) => $q->where('id', $this->id))->count(),

            // Permissions (when loaded)
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'description' => $permission->description,
                        'module' => $permission->module,
                    ];
                })->groupBy('module');
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get human-readable display name for role
     */
    private function getDisplayName(): string
    {
        return match ($this->name) {
            'super-admin' => 'Super Administrador',
            'admin' => 'Administrador',
            'vendor' => 'Vendedor',
            'customer' => 'Cliente',
            default => ucwords(str_replace(['-', '_'], ' ', $this->name)),
        };
    }
}

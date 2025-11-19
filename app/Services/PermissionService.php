<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\Users\UserException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    /**
     * Obtener permisos de un usuario
     */
    public function getUserPermissions(int $userId): array
    {
        $user = User::findOrFail($userId);

        return [
            'from_role' => $user->getPermissionsViaRoles()->pluck('name')->toArray(),
            'direct' => $user->getDirectPermissions()->pluck('name')->toArray(),
            'all' => $user->getAllPermissions()->pluck('name')->toArray(),
        ];
    }

    /**
     * Asignar permisos adicionales a un usuario (no reemplaza los del rol)
     */
    public function assignPermissionsToUser(int $userId, array $permissions): User
    {
        return DB::transaction(function () use ($userId, $permissions) {
            $user = User::findOrFail($userId);

            // Validar que todos los permisos existan
            $this->validatePermissions($permissions);

            // Dar permisos adicionales (se suman a los del rol)
            $user->givePermissionTo($permissions);

            return $user->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Revocar permisos directos de un usuario
     */
    public function revokePermissionsFromUser(int $userId, array $permissions): User
    {
        return DB::transaction(function () use ($userId, $permissions) {
            $user = User::findOrFail($userId);

            // Solo revoca permisos directos, no los del rol
            $user->revokePermissionTo($permissions);

            return $user->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Sincronizar permisos directos (reemplaza todos los directos)
     */
    public function syncPermissionsForUser(int $userId, array $permissions): User
    {
        return DB::transaction(function () use ($userId, $permissions) {
            $user = User::findOrFail($userId);

            // Validar permisos
            $this->validatePermissions($permissions);

            // Sincronizar: elimina todos los directos y asigna los nuevos
            $user->syncPermissions($permissions);

            return $user->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Obtener permisos sugeridos para escalar privilegios de un rol
     */
    public function getSuggestedPermissionsForRole(string $role): array
    {
        return match ($role) {
            'vendor' => [
                'inventory.view.all-warehouses',
                'inventory.manage.all-warehouses',
                'sales.view.all-warehouses',
                'sales.create.all-warehouses',
                'reports.view.all-warehouses',
            ],
            'admin' => [
                'permissions.manage',
                'users.delete',
                'warehouses.delete',
            ],
            default => [],
        };
    }

    /**
     * Verificar si un usuario puede acceder a un almacén específico
     */
    public function canAccessWarehouse(User $user, ?int $warehouseId = null): bool
    {
        // Super-admin y admin siempre pueden
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Si tiene permiso de ver todos los almacenes
        if ($user->hasPermissionTo('inventory.view.all-warehouses')) {
            return true;
        }

        // Si no se especifica almacén, solo puede acceder a su propio almacén
        if ($warehouseId === null) {
            return $user->warehouse_id !== null;
        }

        // Verificar que el almacén solicitado sea el asignado al usuario
        return $user->warehouse_id === $warehouseId;
    }

    /**
     * Obtener IDs de almacenes accesibles para un usuario
     */
    public function getAccessibleWarehouses(User $user)
    {
        // Super-admin y admin ven todos
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return 'all'; // Indicador especial
        }

        // Si tiene permiso de ver todos
        if ($user->hasPermissionTo('inventory.view.all-warehouses')) {
            return 'all';
        }

        // Solo su almacén asignado
        return $user->warehouse_id ? [$user->warehouse_id] : [];
    }

    /**
     * Validar que los permisos existan
     */
    private function validatePermissions(array $permissions): void
    {
        $existingPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
        $invalidPermissions = array_diff($permissions, $existingPermissions);

        if (!empty($invalidPermissions)) {
            throw new \InvalidArgumentException(
                'Permisos inválidos: ' . implode(', ', $invalidPermissions)
            );
        }
    }
}

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
     * 🔥 ACTUALIZADO: Obtener permisos sugeridos para escalar privilegios de un rol
     *
     * Estos son permisos que típicamente querrías dar a un usuario de ese rol
     * para extender sus capacidades sin cambiar su rol base
     */
    public function getSuggestedPermissionsForRole(string $role): array
    {
        return match ($role) {
            'vendor' => [
                // 🔥 Escalado 1: Acceso a múltiples almacenes (pero NO todos)
                // Nota: El super-admin tendría que asignar manualmente almacenes específicos

                // 🔥 Escalado 2: Permisos adicionales de gestión
                'inventory.store',              // Crear registros de inventario
                'inventory.update',             // Modificar inventario
                'inventory.bulk-assign',        // Asignación masiva (de su almacén)
                'products.store',               // Crear productos
                'products.update',              // Editar productos
                'products.images.upload',       // Subir imágenes
                'categories.store',             // Crear categorías
                'categories.update',            // Editar categorías
                'entities.deactivate',          // Desactivar clientes
                'entities.activate',            // Activar clientes

                // 🔥 Escalado 3: Acceso a reportes/estadísticas avanzadas
                'inventory.statistics.global',  // Ver estadísticas globales (de su almacén)
                'products.statistics',          // Estadísticas de productos
            ],

            'admin' => [
                // 🔥 Permisos que solo tiene super-admin
                'permissions.index',            // Ver todos los permisos
                'permissions.user',             // Ver permisos de usuarios
                'permissions.assign',           // Asignar permisos personalizados
                'permissions.revoke',           // Revocar permisos
                'permissions.sync',             // Sincronizar permisos
                'users.destroy',                // Eliminar usuarios
                'users.restore',                // Restaurar usuarios
                'users.change-role',            // Cambiar roles
                'warehouses.destroy',           // Eliminar almacenes
                'stock.sync',                   // Sincronizar inventario global
            ],

            'customer' => [
                // Customers normalmente no necesitan escalado
                // Son usuarios finales del ecommerce
            ],

            default => [],
        };
    }

    /**
     * Verificar si un usuario puede acceder a un almacén específico
     *
     * @param User $user
     * @param int|null $warehouseId
     * @return bool
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
     *
     * @param User $user
     * @return string|array Retorna 'all' si tiene acceso total, o array de IDs
     */
    public function getAccessibleWarehouses(User $user): string|array
    {
        // Super-admin y admin ven todos
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return 'all';
        }

        // Si tiene permiso de ver todos
        if ($user->hasPermissionTo('inventory.view.all-warehouses')) {
            return 'all';
        }

        // Solo su almacén asignado
        return $user->warehouse_id ? [$user->warehouse_id] : [];
    }

    /**
     * 🔥 NUEVO: Verificar si puede gestionar inventario de un almacén
     */
    public function canManageWarehouseInventory(User $user, int $warehouseId): bool
    {
        // Super-admin y admin siempre pueden
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Si tiene permiso de gestionar todos los almacenes
        if ($user->hasPermissionTo('inventory.manage.all-warehouses')) {
            return true;
        }

        // Solo puede gestionar su almacén asignado
        return $user->warehouse_id === $warehouseId;
    }

    /**
     * 🔥 NUEVO: Verificar si puede hacer transferencias entre almacenes específicos
     */
    public function canTransferBetweenWarehouses(User $user, int $fromWarehouseId, int $toWarehouseId): bool
    {
        // Super-admin y admin siempre pueden
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        // Si tiene permiso de transferir entre cualquier almacén
        if ($user->hasPermissionTo('stock.transfer.any')) {
            return true;
        }

        // Debe tener acceso al menos a uno de los almacenes (origen o destino)
        $hasAccessToOrigin = $this->canAccessWarehouse($user, $fromWarehouseId);
        $hasAccessToDestination = $this->canAccessWarehouse($user, $toWarehouseId);

        return $hasAccessToOrigin || $hasAccessToDestination;
    }

    /**
     * 🔥 NUEVO: Obtener permisos peligrosos que requieren aprobación especial
     *
     * Estos permisos NO deben ser sugeridos automáticamente
     */
    public function getDangerousPermissions(): array
    {
        return [
            'permissions.assign',           // Puede dar permisos a otros
            'permissions.revoke',           // Puede quitar permisos
            'users.destroy',                // Eliminar usuarios
            'users.change-role',            // Cambiar roles (escalado crítico)
            'warehouses.destroy',           // Eliminar almacenes (pérdida de datos)
            'inventory.view.all-warehouses', // Rompe restricción de almacén
            'inventory.manage.all-warehouses', // Rompe restricción de almacén
            'stock.transfer.any',           // Puede mover stock libremente
            'stock.sync',                   // Puede alterar todo el inventario
        ];
    }

    /**
     * 🔥 NUEVO: Validar que no se están asignando permisos peligrosos sin autorización
     */
    public function validateSafePermissionAssignment(User $assigningUser, array $permissions): void
    {
        // Solo super-admin puede asignar permisos peligrosos
        if (!$assigningUser->hasRole('super-admin')) {
            $dangerousPermissions = $this->getDangerousPermissions();
            $attemptedDangerous = array_intersect($permissions, $dangerousPermissions);

            if (!empty($attemptedDangerous)) {
                throw new \InvalidArgumentException(
                    'No tienes autorización para asignar los siguientes permisos críticos: ' .
                    implode(', ', $attemptedDangerous)
                );
            }
        }
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

<?php

namespace App\Services;

use App\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RoleService
{
    /**
     * System roles that cannot be deleted (industry standard - Shopify, WooCommerce, Odoo)
     */
    private const SYSTEM_ROLES = ['super-admin', 'admin', 'vendor', 'customer'];

    /**
     * Get roles with filters and pagination
     */
    public function getFiltered(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Role::query()
            ->where('guard_name', 'sanctum')
            ->withCount('permissions');

        // Por defecto excluir rol 'customer' (es para e-commerce, no ERP)
        if (empty($filters['include_customer'])) {
            $query->where('name', '!=', 'customer');
        }

        // Search by name or description
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if (!empty($filters['type'])) {
            if ($filters['type'] === 'system') {
                $query->whereIn('name', self::SYSTEM_ROLES);
            } elseif ($filters['type'] === 'custom') {
                $query->whereNotIn('name', self::SYSTEM_ROLES);
            }
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get role by ID with permissions
     */
    public function getById(int $id): Role
    {
        $role = Role::where('guard_name', 'sanctum')
            ->with('permissions')
            ->withCount('permissions')
            ->find($id);

        if (!$role) {
            throw new \InvalidArgumentException("Rol no encontrado con ID: {$id}");
        }

        return $role;
    }

    /**
     * Create new role with permissions
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            // Validate unique name
            if (Role::where('name', $data['name'])->where('guard_name', 'sanctum')->exists()) {
                throw new \InvalidArgumentException("Ya existe un rol con el nombre: {$data['name']}");
            }

            // Create role with color and description
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'sanctum',
                'color_hex' => $data['color_hex'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            // Sync permissions if provided
            if (!empty($data['permissions'])) {
                $permissions = Permission::whereIn('name', $data['permissions'])
                    ->where('guard_name', 'sanctum')
                    ->get();
                $role->syncPermissions($permissions);
            }

            return $role->load('permissions')->loadCount('permissions');
        });
    }

    /**
     * Update existing role
     */
    public function updateRole(int $id, array $data): Role
    {
        return DB::transaction(function () use ($id, $data) {
            $role = $this->getById($id);

            // System roles cannot have their name changed
            if ($this->isSystemRole($role) && isset($data['name']) && $data['name'] !== $role->name) {
                throw new \InvalidArgumentException("No se puede cambiar el nombre de un rol del sistema");
            }

            // Validate unique name if changed
            if (isset($data['name']) && $data['name'] !== $role->name) {
                if (Role::where('name', $data['name'])->where('guard_name', 'sanctum')->exists()) {
                    throw new \InvalidArgumentException("Ya existe un rol con el nombre: {$data['name']}");
                }
                $role->name = $data['name'];
            }

            // Update color_hex if provided
            if (array_key_exists('color_hex', $data)) {
                $role->color_hex = $data['color_hex'];
            }

            // Update description if provided
            if (array_key_exists('description', $data)) {
                $role->description = $data['description'];
            }

            $role->save();

            // Sync permissions if provided
            if (isset($data['permissions'])) {
                $permissions = Permission::whereIn('name', $data['permissions'])
                    ->where('guard_name', 'sanctum')
                    ->get();
                $role->syncPermissions($permissions);
            }

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return $role->fresh(['permissions'])->loadCount('permissions');
        });
    }

    /**
     * Delete a role
     */
    public function deleteRole(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $role = $this->getById($id);

            // System roles cannot be deleted (industry standard)
            if ($this->isSystemRole($role)) {
                throw new \InvalidArgumentException(
                    "El rol '{$role->name}' es un rol del sistema y no puede ser eliminado. " .
                        "Esta es una práctica estándar en plataformas como Shopify, WooCommerce y Odoo."
                );
            }

            // Check for users assigned to this role using whereHas
            $usersCount = User::whereHas('roles', function ($q) use ($role) {
                $q->where('id', $role->id);
            })->count();

            if ($usersCount > 0) {
                throw new \InvalidArgumentException(
                    "El rol '{$role->name}' tiene {$usersCount} usuario(s) asignado(s). " .
                        "Reasigne los usuarios antes de eliminar el rol."
                );
            }

            $role->delete();

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return true;
        });
    }

    /**
     * Get role statistics
     */
    public function getStatistics(): array
    {
        return Cache::remember('roles_statistics', now()->addMinutes(10), function () {
            // Excluir rol 'customer' (es para e-commerce, no ERP)
            $roles = Role::where('guard_name', 'sanctum')
                ->where('name', '!=', 'customer')
                ->withCount('permissions')
                ->get();

            // Count users per role manually using whereHas
            $rolesWithUserCount = $roles->map(function ($role) {
                $role->users_count = User::whereHas('roles', fn($q) => $q->where('id', $role->id))->count();
                return $role;
            });

            // Roles del sistema ERP (sin customer)
            $erpSystemRoles = ['super-admin', 'admin', 'vendor'];
            $systemRoles = $rolesWithUserCount->filter(fn($r) => in_array($r->name, $erpSystemRoles));
            $customRoles = $rolesWithUserCount->filter(fn($r) => !in_array($r->name, $erpSystemRoles));

            return [
                'total_roles' => $rolesWithUserCount->count(),
                'system_roles' => $systemRoles->count(),
                'custom_roles' => $customRoles->count(),
                'total_users_with_roles' => User::whereHas('roles', fn($q) => $q->whereIn('name', ['super-admin', 'admin', 'vendor']))->count(),
                'roles_with_users' => $rolesWithUserCount->filter(fn($r) => $r->users_count > 0)->count(),
            ];
        });
    }

    /**
     * Get all available permissions grouped by module
     */
    public function getAvailablePermissions(): Collection
    {
        return Permission::where('guard_name', 'sanctum')
            ->get()
            ->groupBy('module')
            ->map(function ($permissions) {
                return $permissions->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'display_name' => $p->display_name,
                        'description' => $p->description,
                    ];
                });
            });
    }

    /**
     * Check if role is a system role
     */
    public function isSystemRole(Role $role): bool
    {
        return in_array($role->name, self::SYSTEM_ROLES);
    }

    /**
     * Get system role names
     */
    public function getSystemRoleNames(): array
    {
        return self::SYSTEM_ROLES;
    }

    /**
     * Clear role statistics cache
     */
    public function clearCache(): void
    {
        Cache::forget('roles_statistics');
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\User\UserException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Obtener usuarios con filtros y paginación
     */
    public function getFiltered(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()->with(['roles']);

        // Búsqueda general
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cellphone', 'like', "%{$search}%");
            });
        }

        // Filtro de rol
        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        } else {
            // Por defecto: solo admin y vendor
            $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'vendor']);
            });
        }

        // Estado activo
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Warehouse
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Ordenamiento
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Obtener usuario por ID
     */
    public function getById(int $id): User
    {
        $user = User::with(['roles', 'entity'])->find($id);

        if (!$user) {
            throw UserException::notFound($id);
        }

        return $user;
    }

    /**
     * Crear nuevo usuario
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Extraer rol
            $roleName = $data['role'] ?? 'customer';
            unset($data['role']);

            // Extraer warehouse_id si existe
            $warehouseId = $data['warehouse_id'] ?? null;
            unset($data['warehouse_id']);

            // Validar email único si se proporciona
            if (!empty($data['email'])) {
                $this->validateUniqueEmail($data['email']);
            }

            // Hashear contraseña si viene en texto plano
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // is_active por defecto
            if (!array_key_exists('is_active', $data)) {
                $data['is_active'] = true;
            }

            // Agregar warehouse_id si existe
            if ($warehouseId) {
                $data['warehouse_id'] = $warehouseId;
            }

            // Crear usuario
            $user = User::create($data);

            // Asignar rol
            $user->assignRole($roleName);

            // Retornar con relaciones
            return $user->fresh(['roles', 'entity']);
        });
    }

    /**
     * Actualizar usuario
     */
    public function updateUser(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $user = $this->getById($id);

            // Extraer rol si viene
            $roleName = null;
            if (isset($data['role'])) {
                $roleName = $data['role'];
                unset($data['role']);
            }

            // Extraer warehouse_id
            $warehouseId = null;
            if (array_key_exists('warehouse_id', $data)) {
                $warehouseId = $data['warehouse_id'];
                unset($data['warehouse_id']);
            }

            // Validar email único si cambió
            if (isset($data['email']) && $data['email'] !== $user->email) {
                $this->validateUniqueEmail($data['email'], $id);
            }

            // Hashear contraseña si se proporciona
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                // No actualizar password si no viene
                unset($data['password']);
            }

            // Actualizar warehouse_id
            if ($warehouseId !== null) {
                $data['warehouse_id'] = $warehouseId;
            }

            // Actualizar datos
            $user->update($data);

            // Cambiar rol si se especificó
            if ($roleName) {
                $user->syncRoles([$roleName]);
            }

            // Retornar actualizado
            $user->refresh();
            $user->load(['roles', 'entity']);

            return $user;
        });
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $user = $this->getById($id);

            // Verificar que no sea el usuario actual
            if (auth()->check() && auth()->id() === $user->id) {
                throw UserException::cannotDeleteSelf();
            }

            // Verificar si tiene ventas registradas
            if ($user->registeredSales()->exists()) {
                throw UserException::hasRelatedRecords($id, 'ventas');
            }

            return $user->delete();
        });
    }

    /**
     * Restaurar usuario eliminado
     */
    public function restore(int $id): User
    {
        $user = User::onlyTrashed()->find($id);

        if (!$user) {
            throw UserException::notFoundInTrash($id);
        }

        $user->restore();
        $user->load(['roles', 'entity']);

        return $user;
    }

    /**
     * Cambiar estado activo
     */
    public function toggleActive(int $id, bool $isActive): User
    {
        $user = $this->getById($id);

        // No permitir desactivar al usuario actual
        if (auth()->check() && auth()->id() === $user->id && !$isActive) {
            throw UserException::cannotDeactivateSelf();
        }

        $user->update(['is_active' => $isActive]);
        $user->refresh();

        return $user;
    }

    /**
     * Cambiar rol de usuario
     */
    public function changeRole(int $id, string $newRole): User
    {
        $user = $this->getById($id);

        // Validar que el rol exista
        if (!in_array($newRole, ['super-admin', 'admin', 'vendor', 'customer'])) {
            throw UserException::invalidRole($newRole);
        }

        $user->syncRoles([$newRole]);
        $user->refresh();
        $user->load('roles');

        return $user;
    }

    /**
     * Validar email único
     */
    private function validateUniqueEmail(string $email, ?int $excludeId = null): void
    {
        $query = User::where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw UserException::duplicateEmail($email);
        }
    }
}

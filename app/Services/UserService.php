<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;


class UserService
{
    public function getFiltered($filters = [], $perPage = 12)
    {
        $query = User::query()->with(['roles']);

        // ----------------------------------------
        // Búsqueda general
        // ----------------------------------------
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cellphone', 'like', "%{$search}%");
            });
        }

        // ----------------------------------------
        // Filtro de rol
        // ----------------------------------------
        if (!empty($filters['role'])) {

            // Si pide un rol específico, filtra por él
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        } else {

            // Valores por defecto ()
            $query->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'vendor']);
            });
        }

        // ----------------------------------------
        // Estado activo
        // ----------------------------------------
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // ----------------------------------------
        // Warehouse (si aplica)
        // ----------------------------------------
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // ----------------------------------------
        // Ordenamiento
        // ----------------------------------------
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query->orderBy($sortBy, $sortOrder);

        // ----------------------------------------
        // Paginación
        // ----------------------------------------
        return $query->paginate($perPage);
    }

    public function createUser(array $data){
        $user = User::create($data);
        return $user;
    }
}

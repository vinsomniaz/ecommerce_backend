<?php

namespace App\Http\Services;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EntityService
{
    // ... los métodos getAll, findById, create, update no cambian ...

    public function getAll(array $filters = []): Collection|LengthAwarePaginator
    {
        $query = Entity::query();
        $this->applyFilters($query, $filters);
        if (isset($filters['with'])) {
            $query->with($filters['with']);
        }
        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }
        return $query->get();
    }

    public function findById(int $id, array $relations = []): ?Entity
    {
        $query = Entity::query();
        if (!empty($relations)) {
            $query->with($relations);
        }
        return $query->find($id);
    }
    
    public function create(array $data): Entity
    {
        return DB::transaction(function () use ($data) {
            if (!isset($data['user_id']) && Auth::check()) {
                $data['user_id'] = Auth::id();
            }
            if (!isset($data['registered_at'])) {
                $data['registered_at'] = now();
            }
            $entity = Entity::create($data);
            return $entity->load(['user', 'ubigeoData']);
        });
    }

    public function update(Entity $entity, array $data): Entity
    {
        return DB::transaction(function () use ($entity, $data) {
            $entity->update($data);
            return $entity->fresh(['user', 'ubigeoData']);
        });
    }
    
    /**
     * Delete an entity (logical delete).
     * Now this method simply deactivates the entity.
     */
    public function delete(Entity $entity): bool
    {
        return $entity->update(['is_active' => false]);
    }

    /**
     * Soft deactivate entity
     */
    public function deactivate(Entity $entity): Entity
    {
        $entity->update(['is_active' => false]);
        return $entity->fresh();
    }

    /**
     * Reactivate entity
     */
    public function activate(Entity $entity): Entity
    {
        $entity->update(['is_active' => true]);
        return $entity->fresh();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        // Search filter (no cambia)
        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('numero_documento', 'like', "%{$term}%")
                    ->orWhere('business_name', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]);
            });
        }
        
        // ... otros filtros como tipo_documento, fechas, etc. no cambian ...
        if (isset($filters['type'])) {
            if ($filters['type'] === 'customer') {
                $query->customers();
            } elseif ($filters['type'] === 'supplier') {
                $query->suppliers();
            }
        }
        if (isset($filters['tipo_documento'])) {
            $query->where('tipo_documento', $filters['tipo_documento']);
        }
        if (isset($filters['registered_from'])) {
            $query->whereDate('registered_at', '>=', $filters['registered_from']);
        }
        if (isset($filters['registered_to'])) {
            $query->whereDate('registered_at', '<=', $filters['registered_to']);
        }

        // === CAMBIO IMPORTANTE: Lógica de filtro de estado ===
        // Si el filtro 'is_active' está presente, lo usamos.
        if (isset($filters['is_active'])) {
             // Esto permite filtrar por ?is_active=1 (activos) o ?is_active=0 (inactivos/archivados)
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        // Si no se pasa el filtro, NO se aplica ninguna restricción de estado,
        // por lo que el GET general traerá tanto activos como inactivos.

        // Order by (no cambia)
        $query->orderBy('registered_at', 'desc');
    }
}
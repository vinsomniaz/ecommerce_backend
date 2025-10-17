<?php

namespace App\Http\Services;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EntityService
{
    /**
     * Get all entities with optional filters
     */
    public function getAll(array $filters = []): Collection|LengthAwarePaginator
    {
        $query = Entity::query();

        // Apply filters
        $this->applyFilters($query, $filters);

        // Load relationships if requested
        if (isset($filters['with'])) {
            $query->with($filters['with']);
        }

        // Pagination
        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query->get();
    }

    /**
     * Find entity by ID
     */
    public function findById(int $id, array $relations = []): ?Entity
    {
        $query = Entity::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * Create a new entity
     */
    public function create(array $data): Entity
    {
        return DB::transaction(function () use ($data) {
            // Set user_id from authenticated user if not provided
            if (!isset($data['user_id']) && Auth::check()) {
                $data['user_id'] = Auth::id();
            }

            // Set registered_at if not provided
            if (!isset($data['registered_at'])) {
                $data['registered_at'] = now();
            }

            // Create entity
            $entity = Entity::create($data);

            return $entity->load(['user', 'ubigeoData']);
        });
    }

    /**
     * Update an entity
     */
    public function update(Entity $entity, array $data): Entity
    {
        return DB::transaction(function () use ($entity, $data) {
            $entity->update($data);
            return $entity->fresh(['user', 'ubigeoData']);
        });
    }

    /**
     * Delete an entity
     */
    public function delete(Entity $entity): bool
    {
        return DB::transaction(function () use ($entity) {
            return $entity->delete();
        });
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
     * Find entity by document
     */
    public function findByDocument(string $tipoDocumento, string $numeroDocumento): ?Entity
    {
        return Entity::where('tipo_documento', $tipoDocumento)
            ->where('numero_documento', $numeroDocumento)
            ->first();
    }

    /**
     * Search entities by term
     */
    public function search(string $term, array $filters = []): Collection|LengthAwarePaginator
    {
        $query = Entity::query();

        // Apply search
        $query->where(function ($q) use ($term) {
            $q->where('numero_documento', 'like', "%{$term}%")
                ->orWhere('business_name', 'like', "%{$term}%")
                ->orWhere('trade_name', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]);
        });

        // Apply additional filters
        $this->applyFilters($query, $filters);

        // Pagination
        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }

        return $query->get();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        // Filter by type
        if (isset($filters['type'])) {
            if ($filters['type'] === 'customer') {
                $query->customers();
            } elseif ($filters['type'] === 'supplier') {
                $query->suppliers();
            } else {
                $query->where('type', $filters['type']);
            }
        }

        // Filter by tipo_persona
        if (isset($filters['tipo_persona'])) {
            $query->where('tipo_persona', $filters['tipo_persona']);
        }

        // Filter by tipo_documento
        if (isset($filters['tipo_documento'])) {
            $query->where('tipo_documento', $filters['tipo_documento']);
        }

        // Filter by is_active
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by estado_sunat
        if (isset($filters['estado_sunat'])) {
            $query->where('estado_sunat', $filters['estado_sunat']);
        }

        // Filter by condicion_sunat
        if (isset($filters['condicion_sunat'])) {
            $query->where('condicion_sunat', $filters['condicion_sunat']);
        }

        // Filter by ubigeo
        if (isset($filters['ubigeo'])) {
            $query->where('ubigeo', $filters['ubigeo']);
        }

        // Order by
        if (isset($filters['order_by'])) {
            $direction = $filters['order_direction'] ?? 'asc';
            $query->orderBy($filters['order_by'], $direction);
        } else {
            $query->latest('registered_at');
        }
    }
}

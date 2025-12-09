<?php

namespace App\Services;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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
            // Make sure 'country' is included if needed by the resource
            if (!in_array('country', $relations)) {
                $relations[] = 'country';
            }
            $query->with($relations);
        } else {
            $query->with('country');
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
     * Delete an entity (logical delete).
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
                ->orWhereRaw("first_name || ' ' || last_name LIKE ?", ["%{$term}%"]);
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
        // Search filter
        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('numero_documento', 'like', "%{$term}%")
                    ->orWhere('business_name', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereRaw("first_name || ' ' || last_name LIKE ?", ["%{$term}%"]);
            });
        }

        // === CORRECCIÓN DE LÓGICA DE FILTRADO POR TIPO ===
        if (isset($filters['type'])) {
            $type = $filters['type'];

            if ($type === 'customer') {
                $query->customers(); // Usa el scope para 'customer' y 'both'
            } elseif ($type === 'supplier') {
                $query->suppliers(); // Usa el scope para 'supplier' y 'both'
            }
        }

        // Filter by registration date
        if (isset($filters['registered_from'])) {
            $query->whereDate('registered_at', '>=', $filters['registered_from']);
        }
        if (isset($filters['registered_to'])) {
            $query->whereDate('registered_at', '<=', $filters['registered_to']);
        }

        // Filter by is_active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Other filters
        if (isset($filters['tipo_documento'])) {
            $query->where('tipo_documento', $filters['tipo_documento']);
        }

        // Order by
        $query->orderBy('registered_at', 'desc');
    }

    /**
     * Get global statistics for entities with caching
     */
    public function getGlobalStatistics(): array
    {
        $version = Cache::remember('entities_version', now()->addDay(), fn() => 1);
        $key = "entities_global_stats_v{$version}";

        return Cache::remember($key, now()->addMinutes(5), function () {
            return [
                'total_entities' => Entity::count(),
                'active_entities' => Entity::where('is_active', true)->count(),
                'inactive_entities' => Entity::where('is_active', false)->count(),
                'total_customers' => Entity::customers()->count(),
                'total_suppliers' => Entity::suppliers()->count(),
                'total_both' => Entity::where('type', 'both')->count(),
            ];
        });
    }
}

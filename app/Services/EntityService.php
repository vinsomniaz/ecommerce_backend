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
        } else {
            // Siempre cargar documentType por defecto
            $query->with('documentType');
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

        // Asegurar que documentType siempre esté incluido
        if (!in_array('documentType', $relations)) {
            $relations[] = 'documentType';
        }

        // Asegurar que primaryAddress y primaryContact estén incluidos
        if (!in_array('primaryAddress', $relations) && !in_array('primaryAddress.ubigeoData', $relations)) {
            $relations[] = 'primaryAddress.ubigeoData';
        }
        if (!in_array('primaryContact', $relations)) {
            $relations[] = 'primaryContact';
        }

        if (!empty($relations)) {
            $query->with($relations);
        } else {
            $query->with(['primaryAddress.ubigeoData', 'primaryContact', 'documentType']);
        }

        return $query->find($id);
    }

    /**
     * Create a new entity (or restore if exists deleted)
     */
    public function create(array $data): Entity
    {
        return DB::transaction(function () use ($data) {
            // Extraer datos de entity
            $entityData = $data['entity'] ?? $data;

            // Verificar si existe entity eliminada con este numero_documento
            if (!empty($entityData['numero_documento'])) {
                $deletedEntity = Entity::onlyTrashed()
                    ->where('numero_documento', $entityData['numero_documento'])
                    ->first();

                if ($deletedEntity) {
                    // Restaurar y actualizar la entity existente
                    return $this->restoreAndUpdateEntity($deletedEntity, $data);
                }
            }

            // Set user_id from authenticated user if not provided
            if (!isset($entityData['user_id']) && Auth::check()) {
                $entityData['user_id'] = Auth::id();
            }

            // Set registered_at if not provided
            if (!isset($entityData['registered_at'])) {
                $entityData['registered_at'] = now();
            }

            // Create entity
            $entity = Entity::create($entityData);

            // Create addresses
            if (isset($data['addresses']) && is_array($data['addresses'])) {
                foreach ($data['addresses'] as $addressData) {
                    $entity->addresses()->create($addressData);
                }
            }

            // Create contacts
            if (isset($data['contacts']) && is_array($data['contacts'])) {
                foreach ($data['contacts'] as $contactData) {
                    $entity->contacts()->create($contactData);
                }
            }

            return $entity->load([
                'addresses',
                'contacts',
                'primaryAddress.ubigeoData',
                'primaryContact',
                'documentType'
            ]);
        });
    }

    /**
     * Restore deleted entity and update its data
     */
    private function restoreAndUpdateEntity(Entity $entity, array $data): Entity
    {
        // Restaurar entity (esto también restaura addresses y contacts por el observer)
        $entity->restore();

        // Extraer datos de entity
        $entityData = $data['entity'] ?? $data;

        // Actualizar datos de la entity
        $entity->update($entityData);

        // Sincronizar addresses si se envían
        if (isset($data['addresses']) && is_array($data['addresses'])) {
            // Eliminar direcciones antiguas y crear nuevas
            $entity->addresses()->forceDelete();
            foreach ($data['addresses'] as $addressData) {
                $entity->addresses()->create($addressData);
            }
        }

        // Sincronizar contacts si se envían
        if (isset($data['contacts']) && is_array($data['contacts'])) {
            // Eliminar contactos antiguos y crear nuevos
            $entity->contacts()->forceDelete();
            foreach ($data['contacts'] as $contactData) {
                $entity->contacts()->create($contactData);
            }
        }

        return $entity->load([
            'addresses',
            'contacts',
            'primaryAddress.ubigeoData',
            'primaryContact',
            'documentType'
        ]);
    }

    /**
     * Update an entity
     */
    public function update(Entity $entity, array $data): Entity
    {
        return DB::transaction(function () use ($entity, $data) {
            // Update entity data if provided
            if (isset($data['entity'])) {
                $entity->update($data['entity']);
            }

            // Update addresses (Smart Sync)
            if (isset($data['addresses']) && is_array($data['addresses'])) {
                $currentIds = $entity->addresses()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['addresses'] as $addressData) {
                    if (isset($addressData['id']) && in_array($addressData['id'], $currentIds)) {
                        // Update existing - remove 'id' from update data
                        $addressId = $addressData['id'];
                        unset($addressData['id']);
                        $incomingIds[] = $addressId;

                        // Only update if there's data to update
                        if (!empty($addressData)) {
                            $entity->addresses()->where('id', $addressId)->update($addressData);
                        }
                    } elseif (!isset($addressData['id'])) {
                        // Create new only if no ID was provided
                        $newAddress = $entity->addresses()->create($addressData);
                        $incomingIds[] = $newAddress->id;
                    }
                    // If ID is provided but not found in currentIds, skip (invalid ID)
                }

                // Delete missing (Soft Delete)
                $toDelete = array_diff($currentIds, $incomingIds);
                if (!empty($toDelete)) {
                    $entity->addresses()->whereIn('id', $toDelete)->delete();
                }
            }

            // Update contacts (Smart Sync)
            if (isset($data['contacts']) && is_array($data['contacts'])) {
                $currentIds = $entity->contacts()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['contacts'] as $contactData) {
                    if (isset($contactData['id']) && in_array($contactData['id'], $currentIds)) {
                        // Update existing - remove 'id' from update data
                        $contactId = $contactData['id'];
                        unset($contactData['id']);
                        $incomingIds[] = $contactId;

                        // Only update if there's data to update
                        if (!empty($contactData)) {
                            $entity->contacts()->where('id', $contactId)->update($contactData);
                        }
                    } elseif (!isset($contactData['id'])) {
                        // Create new only if no ID was provided
                        $newContact = $entity->contacts()->create($contactData);
                        $incomingIds[] = $newContact->id;
                    }
                    // If ID is provided but not found in currentIds, skip (invalid ID)
                }

                // Delete missing (Soft Delete)
                $toDelete = array_diff($currentIds, $incomingIds);
                if (!empty($toDelete)) {
                    $entity->contacts()->whereIn('id', $toDelete)->delete();
                }
            }

            // Refresh and load relationships
            $entity->refresh();
            $entity->load([
                'addresses',
                'contacts',
                'primaryAddress.ubigeoData',
                'primaryContact',
                'documentType'
            ]);

            return $entity;
        });
    }

    /**
     * Delete an entity (soft delete).
     */
    public function delete(Entity $entity): bool
    {
        return $entity->delete();
    }

    /**
     * Soft deactivate entity
     */
    public function deactivate(Entity $entity): Entity
    {
        $entity->update(['is_active' => false]);
        $entity->refresh();
        return $entity;
    }

    /**
     * Reactivate entity
     */
    public function activate(Entity $entity): Entity
    {
        $entity->update(['is_active' => true]);
        $entity->refresh();
        return $entity;
    }

    /**
     * Restore a soft-deleted entity
     */
    public function restore(int $id): ?Entity
    {
        $entity = Entity::withTrashed()->find($id);

        if (!$entity || !$entity->trashed()) {
            return null;
        }

        $entity->restore();

        return $entity->load([
            'addresses',
            'contacts',
            'primaryAddress.ubigeoData',
            'primaryContact',
            'documentType'
        ]);
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
                ->orWhereRaw("first_name || ' ' || last_name LIKE ?", ["%{$term}%"])
                // Buscar en email de contacto principal
                ->orWhereHas('primaryContact', function ($q) use ($term) {
                    $q->where('email', 'like', "%{$term}%");
                });
        });

        // Apply additional filters
        $this->applyFilters($query, $filters);

        // Cargar relaciones
        $query->with(['documentType', 'primaryAddress', 'primaryContact']);

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
                    ->orWhereRaw("first_name || ' ' || last_name LIKE ?", ["%{$term}%"])
                    // Buscar en email de contacto principal
                    ->orWhereHas('primaryContact', function ($q) use ($term) {
                        $q->where('email', 'like', "%{$term}%");
                    });
            });
        }

        // Filter by type
        if (isset($filters['type'])) {
            $type = $filters['type'];

            if ($type === 'customer') {
                $query->customers();
            } elseif ($type === 'supplier') {
                $query->suppliers();
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

        // Filter by document type
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

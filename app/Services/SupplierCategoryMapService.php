<?php

namespace App\Services;

use App\Models\SupplierCategoryMap;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{Cache, Log};

class SupplierCategoryMapService
{
    /**
     * Obtiene mapeos de categorías con filtros y paginación (cacheado)
     */
    public function getMaps(Request $request): LengthAwarePaginator
    {
        $supplierId = $request->query('supplier_id');
        $categoryId = $request->query('category_id');
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;
        $unmapped = $request->has('unmapped') ? $request->boolean('unmapped') : null;
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);

        $version = Cache::remember('supplier_category_maps_version', now()->addDay(), fn() => 1);

        $cacheKey = "supplier_category_maps_v{$version}_" . md5(serialize([
            $supplierId,
            $categoryId,
            $isActive,
            $unmapped,
            $perPage,
            $page,
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($supplierId, $categoryId, $isActive, $unmapped, $perPage) {
            $query = SupplierCategoryMap::with(['supplier', 'category'])
                ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
                // Filtro por categoría (incluyendo subcategorías)
                ->when($categoryId, function ($q) use ($categoryId) {
                    $category = Category::with('children.children')->find($categoryId);
                    if ($category) {
                        $categoryIds = $category->getAllDescendantIdsWithCache();
                        $q->whereIn('category_id', $categoryIds);
                    }
                })
                ->when($isActive !== null, fn($q) => $q->where('is_active', $isActive))
                ->when($unmapped !== null, function ($q) use ($unmapped) {
                    if ($unmapped) {
                        $q->whereNull('category_id');
                    } else {
                        $q->whereNotNull('category_id');
                    }
                })
                ->latest();

            return $query->paginate($perPage);
        });
    }

    /**
     * Obtiene estadísticas de mapeos
     */
    public function getStats(?int $supplierId = null): array
    {
        $version = Cache::get('supplier_category_maps_version', 1);
        $statsKey = "supplier_category_maps_stats_v{$version}_" . ($supplierId ?? 'all');

        return Cache::remember($statsKey, now()->addMinutes(30), function () use ($supplierId) {
            $query = SupplierCategoryMap::query();

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $total = $query->count();
            $mapped = (clone $query)->whereNotNull('category_id')->count();
            $unmapped = (clone $query)->whereNull('category_id')->count();
            $active = (clone $query)->where('is_active', true)->count();
            $inactive = (clone $query)->where('is_active', false)->count();

            return [
                'total' => $total,
                'mapped' => $mapped,
                'unmapped' => $unmapped,
                'active' => $active,
                'inactive' => $inactive,
                'mapping_rate' => $total > 0 ? round(($mapped / $total) * 100, 2) : 0,
            ];
        });
    }

    /**
     * Obtiene mapeo por ID
     */
    public function getById(int $id): SupplierCategoryMap
    {
        return SupplierCategoryMap::with(['supplier', 'category'])->findOrFail($id);
    }

    /**
     * Crea o actualiza un mapeo
     */
    public function createOrUpdate(array $data): SupplierCategoryMap
    {
        $map = SupplierCategoryMap::updateOrCreate(
            [
                'supplier_id' => $data['supplier_id'],
                'supplier_category' => $data['supplier_category'],
            ],
            [
                'category_id' => $data['category_id'] ?? null,
                'confidence' => $data['confidence'] ?? 0.5,
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        Log::info('Mapeo de categoría creado/actualizado', [
            'id' => $map->id,
            'supplier_category' => $map->supplier_category,
            'was_created' => $map->wasRecentlyCreated,
        ]);

        return $map;
    }

    /**
     * Actualiza un mapeo existente
     */
    public function update(int $id, array $data): SupplierCategoryMap
    {
        $map = SupplierCategoryMap::findOrFail($id);
        $map->update($data);

        return $map->fresh(['supplier', 'category']);
    }

    /**
     * Elimina un mapeo
     */
    public function delete(int $id): bool
    {
        $map = SupplierCategoryMap::findOrFail($id);
        return $map->delete();
    }

    /**
     * Mapeo masivo de categorías
     */
    public function bulkMap(array $mappings): int
    {
        $updated = 0;
        foreach ($mappings as $mapping) {
            SupplierCategoryMap::where('id', $mapping['id'])
                ->update([
                    'category_id' => $mapping['category_id'],
                    'confidence' => 1.0, // Mapeo manual = 100% confianza
                ]);
            $updated++;
        }

        // Invalidar caché
        Cache::increment('supplier_category_maps_version');

        Log::info('Mapeo masivo de categorías', [
            'updated_count' => $updated,
        ]);

        return $updated;
    }

    /**
     * Obtiene categorías sin mapear
     */
    public function getUnmapped(Request $request): LengthAwarePaginator
    {
        $perPage = $request->query('per_page', 15);

        $query = SupplierCategoryMap::with('supplier')
            ->whereNull('category_id')
            ->where('is_active', true)
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest();

        return $query->paginate($perPage);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierCategoryMap;
use App\Http\Resources\SupplierCategoryMapResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierCategoryMapController extends Controller
{
    /**
     * Listar mapeos de categorías
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupplierCategoryMap::with(['supplier', 'category'])
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('unmapped'), function ($q) use ($request) {
                if ($request->boolean('unmapped')) {
                    $q->whereNull('category_id');
                } else {
                    $q->whereNotNull('category_id'); // unmapped=false = solo mapeadas
                }
            })
            ->latest();

        $maps = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $maps->items(),
            'meta' => [
                'current_page' => $maps->currentPage(),
                'last_page' => $maps->lastPage(),
                'per_page' => $maps->perPage(),
                'total' => $maps->total(),
            ],
        ]);
    }

    /**
     * Ver detalle de mapeo
     */
    public function show(SupplierCategoryMap $map): JsonResponse
    {
        $map->load(['supplier', 'category']);

        return response()->json([
            'success' => true,
            'data' => new SupplierCategoryMapResource($map),
        ]);
    }

    /**
     * Crear mapeo manual
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:entities,id',
            'supplier_category' => 'required|string|max:160',
            'category_id' => 'nullable|exists:categories,id',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'is_active' => 'sometimes|boolean',
        ]);

        // Usar updateOrCreate para evitar duplicados
        $map = SupplierCategoryMap::updateOrCreate(
            [
                'supplier_id' => $validated['supplier_id'],
                'supplier_category' => $validated['supplier_category'],
            ],
            [
                'category_id' => $validated['category_id'] ?? null,
                'confidence' => $validated['confidence'] ?? 0.5,
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        $wasRecentlyCreated = $map->wasRecentlyCreated;

        return response()->json([
            'success' => true,
            'message' => $wasRecentlyCreated
                ? 'Mapeo de categoría creado exitosamente'
                : 'Mapeo de categoría actualizado exitosamente',
            'data' => new SupplierCategoryMapResource($map->load(['supplier', 'category'])),
        ], $wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Actualizar mapeo
     */
    public function update(Request $request, SupplierCategoryMap $map): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $map->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Mapeo de categoría actualizado exitosamente',
            'data' => new SupplierCategoryMapResource($map->fresh(['supplier', 'category'])),
        ]);
    }

    /**
     * Eliminar mapeo
     */
    public function destroy(SupplierCategoryMap $map): JsonResponse
    {
        $map->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mapeo de categoría eliminado exitosamente',
        ]);
    }

    /**
     * Mapeo masivo de categorías
     */
    public function bulkMap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*.id' => 'required|exists:supplier_category_maps,id',
            'mappings.*.category_id' => 'required|exists:categories,id',
        ]);

        $updated = 0;
        foreach ($validated['mappings'] as $mapping) {
            SupplierCategoryMap::where('id', $mapping['id'])
                ->update([
                    'category_id' => $mapping['category_id'],
                    'confidence' => 1.0, // Mapeo manual = 100% confianza
                ]);
            $updated++;
        }

        return response()->json([
            'success' => true,
            'message' => "Se mapearon {$updated} categorías exitosamente",
            'data' => [
                'updated' => $updated,
            ],
        ]);
    }

    /**
     * Obtener categorías sin mapear
     */
    public function unmapped(Request $request): JsonResponse
    {
        $query = SupplierCategoryMap::with('supplier')
            ->whereNull('category_id')
            ->where('is_active', true)
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest();

        $maps = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $maps->items(),
            'meta' => [
                'current_page' => $maps->currentPage(),
                'last_page' => $maps->lastPage(),
                'per_page' => $maps->perPage(),
                'total' => $maps->total(),
            ],
        ]);
    }

    /**
     * Estadísticas de mapeos
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = SupplierCategoryMap::query();

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $total = $query->count();
        $mapped = (clone $query)->whereNotNull('category_id')->count();
        $unmapped = (clone $query)->whereNull('category_id')->count();
        $active = (clone $query)->where('is_active', true)->count();
        $inactive = (clone $query)->where('is_active', false)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'mapped' => $mapped,
                'unmapped' => $unmapped,
                'active' => $active,
                'inactive' => $inactive,
                'mapping_rate' => $total > 0 ? round(($mapped / $total) * 100, 2) : 0,
            ],
        ]);
    }
}

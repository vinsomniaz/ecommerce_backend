<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierCategoryMap;
use App\Http\Resources\SupplierCategoryMapResource;
use App\Http\Resources\SupplierCategoryMapCollection;
use App\Services\SupplierCategoryMapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierCategoryMapController extends Controller
{
    public function __construct(
        private SupplierCategoryMapService $supplierCategoryMapService
    ) {}

    /**
     * Listar mapeos de categorías (con caché y estadísticas)
     */
    public function index(Request $request): JsonResponse
    {
        $maps = $this->supplierCategoryMapService->getMaps($request);
        $collection = new SupplierCategoryMapCollection($maps);

        return response()->json([
            'success' => true,
            'message' => 'Mapeos de categorías obtenidos correctamente',
            'data' => $collection->toArray($request)['data'],
            'meta' => $collection->with($request)['meta'],
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

        $map = $this->supplierCategoryMapService->createOrUpdate($validated);
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

        $map = $this->supplierCategoryMapService->update($map->id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Mapeo de categoría actualizado exitosamente',
            'data' => new SupplierCategoryMapResource($map),
        ]);
    }

    /**
     * Eliminar mapeo
     */
    public function destroy(SupplierCategoryMap $map): JsonResponse
    {
        $this->supplierCategoryMapService->delete($map->id);

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

        $updated = $this->supplierCategoryMapService->bulkMap($validated['mappings']);

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
        $maps = $this->supplierCategoryMapService->getUnmapped($request);

        return response()->json([
            'success' => true,
            'message' => 'Categorías sin mapear obtenidas correctamente',
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
        $stats = $this->supplierCategoryMapService->getStats($request->supplier_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}

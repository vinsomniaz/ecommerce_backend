<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Http\Resources\Warehouses\WarehouseResource;
use App\Http\Resources\Warehouses\WarehouseCollection;
use App\Services\WarehouseService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(
        private WarehouseService $warehouseService,
        private PermissionService $permissionService
    ) {}

    /**
     * Listar almacenes con filtrado automático según permisos
     *
     * @group Almacenes
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['is_active', 'visible_online', 'is_main']);

        // Solo filtrar si no tiene acceso a todos
        if (!$user->hasPermissionTo('warehouses.view.all')) {
            $accessibleWarehouses = $this->permissionService->getAccessibleWarehouses($user);

            if ($accessibleWarehouses !== 'all') {
                $filters['warehouse_ids'] = $accessibleWarehouses;
            }
        }

        $warehouses = $this->warehouseService->list($filters);

        if ($warehouses->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aún no se ha creado ningún almacén',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'global_stats' => $this->warehouseService->getGlobalStatistics(),
                ],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Almacenes obtenidos correctamente',
            'data' => WarehouseResource::collection($warehouses),
            'meta' => [
                'total' => $warehouses->count(),
                'page_stats' => [
                    'active_in_page' => $warehouses->where('is_active', true)->count(),
                    'inactive_in_page' => $warehouses->where('is_active', false)->count(),
                    'visible_online_in_page' => $warehouses->where('visible_online', true)->count(),
                ],
                'global_stats' => $this->warehouseService->getGlobalStatistics(),
            ],
        ]);
    }

    /**
     * Crear almacén
     *
     * @group Almacenes
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseService->create($request->validated());

        // Limpiar cache al crear
        $this->warehouseService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Almacén creado exitosamente',
            'data' => new WarehouseResource($warehouse),
        ], 201);
    }

    /**
     * Ver almacén con validación de acceso
     *
     * @group Almacenes
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Solo validar si no tiene acceso a todos
        if (!$user->hasPermissionTo('warehouses.view.all')) {
            if (!$this->permissionService->canAccessWarehouse($user, $id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este almacén',
                ], 403);
            }
        }

        $warehouse = $this->warehouseService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Almacén obtenido correctamente',
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /**
     * Actualizar almacén
     *
     * @group Almacenes
     */
    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        $warehouse = $this->warehouseService->update($id, $request->validated());

        // Limpiar cache al actualizar
        $this->warehouseService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Almacén actualizado exitosamente',
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /**
     * Eliminar almacén
     *
     * @group Almacenes
     */
    public function destroy(int $id): JsonResponse
    {
        $this->warehouseService->delete($id);

        // Limpiar cache al eliminar
        $this->warehouseService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Almacén eliminado exitosamente',
        ]);
    }

    /**
     * Estadísticas globales de almacenes
     *
     * @group Almacenes
     */
    public function globalStatistics(): JsonResponse
    {
        $stats = $this->warehouseService->getGlobalStatistics();

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas obtenidas correctamente',
            'data' => $stats,
        ]);
    }
}


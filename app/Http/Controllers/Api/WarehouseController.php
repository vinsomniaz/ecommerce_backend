<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Http\Resources\Warehouses\WarehouseResource;
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

        // 🔥 SOLO FILTRADO: Si no tiene acceso a todos, agregar filtro
        if (!$user->hasPermissionTo('warehouses.view.all')) {
            $accessibleWarehouses = $this->permissionService->getAccessibleWarehouses($user);

            if ($accessibleWarehouses !== 'all') {
                $filters['warehouse_ids'] = $accessibleWarehouses;
            }
        }

        $warehouses = $this->warehouseService->list($filters);

        return response()->json([
            'success' => true,
            'data' => WarehouseResource::collection($warehouses),
            'meta' => [
                'total' => $warehouses->count(),
            ],
        ]);
    }

    /**
     * Crear almacén
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        // ✅ No validar permisos: la ruta ya lo hizo con middleware('permission:warehouses.store')

        $warehouse = $this->warehouseService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Almacén creado exitosamente',
            'data' => new WarehouseResource($warehouse),
        ], 201);
    }

    /**
     * Ver almacén con validación de acceso
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // 🔥 SOLO VALIDAR SI NO TIENE ACCESO A TODOS
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
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /**
     * Actualizar almacén
     */
    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        // ✅ No validar permisos: la ruta ya lo hizo

        $warehouse = $this->warehouseService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Almacén actualizado exitosamente',
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /**
     * Eliminar almacén
     */
    public function destroy(int $id): JsonResponse
    {
        // ✅ No validar permisos: la ruta ya lo hizo con middleware('permission:warehouses.destroy')

        $this->warehouseService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Almacén eliminado exitosamente',
        ]);
    }
}

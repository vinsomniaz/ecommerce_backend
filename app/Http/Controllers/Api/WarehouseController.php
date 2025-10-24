<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Http\Resources\Warehouses\WarehouseResource;
use App\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(
        private WarehouseService $warehouseService
    ) {}

    /**
     * Listar almacenes
     *
     * @group Almacenes
     * @queryParam is_active boolean Filtrar por estado activo. Example: 1
     * @queryParam visible_online boolean Filtrar por visibilidad online. Example: 1
     * @queryParam is_main boolean Filtrar almacén principal. Example: 1
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'visible_online', 'is_main']);
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
     * Crear nuevo almacén
     *
     * @group Almacenes
     * @bodyParam name string required Nombre del almacén. Example: Almacén Principal
     * @bodyParam ubigeo string required Código ubigeo de 6 dígitos. Example: 150101
     * @bodyParam address string required Dirección del almacén. Example: Av. Ejemplo 123, Lima
     * @bodyParam phone string optional Número de teléfono. Example: 987654321
     * @bodyParam is_main boolean optional Marcar como almacén principal. Example: true
     * @bodyParam is_active boolean optional Estado activo. Example: true
     * @bodyParam visible_online boolean optional Visible en ecommerce. Example: true
     * @bodyParam picking_priority integer optional Prioridad de picking (0-100). Example: 1
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Almacén creado exitosamente',
            'data' => new WarehouseResource($warehouse),
        ], 201);
    }

    /**
     * Obtener detalle de almacén
     *
     * @group Almacenes
     * @urlParam id integer required ID del almacén. Example: 1
     */
    public function show(int $id): JsonResponse
    {
        $warehouse = $this->warehouseService->getById($id);

        return response()->json([
            'success' => true,
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    /**
     * Actualizar almacén
     *
     * @group Almacenes
     * @urlParam id integer required ID del almacén. Example: 1
     * @bodyParam name string optional Nombre del almacén. Example: Almacén Secundario
     * @bodyParam ubigeo string optional Código ubigeo de 6 dígitos. Example: 150102
     * @bodyParam address string optional Dirección del almacén. Example: Jr. Nueva 456
     * @bodyParam phone string optional Número de teléfono. Example: 987654321
     * @bodyParam is_main boolean optional Marcar como almacén principal. Example: false
     * @bodyParam is_active boolean optional Estado activo. Example: true
     * @bodyParam visible_online boolean optional Visible en ecommerce. Example: true
     * @bodyParam picking_priority integer optional Prioridad de picking (0-100). Example: 5
     */
    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        $warehouse = $this->warehouseService->update($id, $request->validated());

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
     * @urlParam id integer required ID del almacén. Example: 1
     */
    public function destroy(int $id): JsonResponse
    {
        $this->warehouseService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Almacén eliminado exitosamente',
        ]);
    }
}

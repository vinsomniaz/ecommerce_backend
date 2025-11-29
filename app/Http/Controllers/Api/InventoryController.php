<?php
// app/Http/Controllers/Api/InventoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryRequest;
use App\Http\Requests\Inventory\BulkAssignInventoryRequest;
use App\Http\Resources\Inventory\InventoryResource;
use App\Services\InventoryService;
use App\Models\Product;
use App\Models\Warehouse;
use App\Exceptions\Inventory\InventoryAlreadyExistsException;
use App\Exceptions\Inventory\InventoryNotFoundException;
use App\Exceptions\Inventory\InventoryHasStockException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * Listar todo el inventario con filtros avanzados
     * GET /api/inventory
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'warehouse_id' => $request->query('warehouse_id'),
            'category_id' => $request->query('category_id'), // ✅ Nuevo filtro
            'search' => $request->query('search'),
            'with_stock' => $request->query('with_stock'),
            'low_stock' => $request->query('low_stock'),
            'out_of_stock' => $request->query('out_of_stock'),
            'sort_by' => $request->query('sort_by', 'last_movement_at'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $inventory = $this->inventoryService->getFiltered($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Inventario obtenido correctamente',
            'data' => InventoryResource::collection($inventory->items()),
            'meta' => [
                'current_page' => $inventory->currentPage(),
                'per_page' => $inventory->perPage(),
                'total' => $inventory->total(),
                'last_page' => $inventory->lastPage(),
                'warehouse_id' => $filters['warehouse_id'],
                'warehouse_name' => $this->getWarehouseName($filters['warehouse_id']), // Opcional
            ]
        ], 200);
    }

    /**
     * Obtener nombre del almacén (opcional, para metadata)
     */
    private function getWarehouseName(?int $warehouseId): ?string
    {
        if (!$warehouseId) {
            return null;
        }

        return Warehouse::find($warehouseId)?->name;
    }
    /**
     * Obtener inventario de un producto específico en todos los almacenes
     * GET /api/products/{product}/inventory
     */
    public function getByProduct(Product $product): JsonResponse
    {
        $inventory = $this->inventoryService->getByProduct($product->id);

        return response()->json([
            'success' => true,
            'data' => InventoryResource::collection($inventory),
            'meta' => [
                'total_warehouses' => count($inventory),
                'product_id' => $product->id,
                'product_name' => $product->primary_name,
            ],
        ]);
    }

    /**
     * Obtener inventario de un almacén específico
     * GET /api/warehouses/{warehouse}/inventory
     */
    public function getByWarehouse(Warehouse $warehouse, Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id', 'with_stock', 'low_stock']);
        $perPage = $request->input('per_page', 15);

        $inventory = $this->inventoryService->getByWarehouse($warehouse->id, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => InventoryResource::collection($inventory->items()),
            'meta' => [
                'current_page' => $inventory->currentPage(),
                'per_page' => $inventory->perPage(),
                'total' => $inventory->total(),
                'last_page' => $inventory->lastPage(),
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
            ],
        ]);
    }

    /**
     * Obtener inventario específico (producto + almacén)
     * GET /api/inventory/{product}/{warehouse}
     */
    public function show(Product $product, Warehouse $warehouse): JsonResponse
    {
        try {
            $inventory = $this->inventoryService->getSpecific($product->id, $warehouse->id);

            return response()->json([
                'success' => true,
                'data' => new InventoryResource($inventory),
            ]);
        } catch (InventoryNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Asignar producto a almacén(es)
     * POST /api/inventory
     *
     * Body:
     * {
     *   "product_id": 1,
     *   "warehouse_ids": [1, 2, 3],
     *   "sale_price": 100.00,
     *   "profit_margin": 30,
     *   "min_sale_price": 85.00
     * }
     */
    public function store(StoreInventoryRequest $request): JsonResponse
    {
        try {
            $result = $this->inventoryService->assignToWarehouses(
                $request->product_id,
                $request->warehouse_ids,
                $request->only(['sale_price', 'profit_margin', 'min_sale_price'])
            );

            return response()->json([
                'success' => true,
                'message' => "Producto asignado a {$result['assigned']} almacén(es) exitosamente",
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar producto a almacenes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asignación masiva de múltiples productos a múltiples almacenes
     * POST /api/inventory/bulk-assign
     *
     * Body:
     * {
     *   "product_ids": [1, 2, 3],
     *   "warehouse_ids": [1, 2],
     *   "sale_price": 100.00,
     *   "profit_margin": 30
     * }
     */
    public function bulkAssign(BulkAssignInventoryRequest $request): JsonResponse
    {
        try {
            $result = $this->inventoryService->bulkAssign(
                $request->product_ids,
                $request->warehouse_ids,
                $request->only(['sale_price', 'profit_margin', 'min_sale_price'])
            );

            return response()->json([
                'success' => true,
                'message' => "Asignación masiva completada: {$result['total_assigned']} registros creados",
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la asignación masiva',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar configuración de inventario (precios, etc.)
     * PUT/PATCH /api/inventory/{product}/{warehouse}
     */
    public function update(
        UpdateInventoryRequest $request,
        Product $product,
        Warehouse $warehouse
    ): JsonResponse {
        try {
            $inventory = $this->inventoryService->update(
                $product->id,
                $warehouse->id,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Inventario actualizado exitosamente',
                'data' => new InventoryResource($inventory),
            ]);
        } catch (InventoryNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar inventario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar inventario (desasignar producto de almacén)
     * DELETE /api/inventory/{product}/{warehouse}
     */
    public function destroy(Product $product, Warehouse $warehouse): JsonResponse
    {
        try {
            $this->inventoryService->delete($product->id, $warehouse->id);

            return response()->json([
                'success' => true,
                'message' => 'Producto desasignado del almacén exitosamente',
            ]);
        } catch (InventoryHasStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (InventoryNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar inventario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estadísticas de inventario de un producto
     * GET /api/products/{product}/inventory/statistics
     */
    public function productStatistics(Product $product): JsonResponse
    {
        $stats = $this->inventoryService->getProductStatistics($product->id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Estadísticas de inventario de un almacén
     * GET /api/warehouses/{warehouse}/inventory/statistics
     */
    public function warehouseStatistics(Warehouse $warehouse): JsonResponse
    {
        $stats = $this->inventoryService->getWarehouseStatistics($warehouse->id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Estadísticas globales de inventario
     * GET /api/inventory/statistics/global
     */
    public function globalStatistics(): JsonResponse
    {
        $stats = $this->inventoryService->getGlobalStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Alerta de productos con stock bajo
     * GET /api/inventory/alerts/low-stock
     */
    public function lowStockAlert(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $inventory = $this->inventoryService->getLowStockItems($perPage);

        return response()->json([
            'success' => true,
            'data' => InventoryResource::collection($inventory->items()),
            'meta' => [
                'current_page' => $inventory->currentPage(),
                'total' => $inventory->total(),
                'last_page' => $inventory->lastPage(),
            ],
        ]);
    }

    /**
     * Alerta de productos sin stock
     * GET /api/inventory/alerts/out-of-stock
     */
    public function outOfStockAlert(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $inventory = $this->inventoryService->getOutOfStockItems($perPage);

        return response()->json([
            'success' => true,
            'data' => InventoryResource::collection($inventory->items()),
            'meta' => [
                'current_page' => $inventory->currentPage(),
                'total' => $inventory->total(),
                'last_page' => $inventory->lastPage(),
            ],
        ]);
    }
}

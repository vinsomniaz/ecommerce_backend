<?php
// app/Http/Controllers/Api/StockManagementController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class StockManagementController extends Controller
{
    public function __construct(
        private StockManagementService $stockService
    ) {}

    /**
     * Traslado de stock entre almacenes
     * POST /api/stock/transfer
     *
     * Body:
     * {
     *   "product_id": 1,
     *   "from_warehouse_id": 1,
     *   "to_warehouse_id": 2,
     *   "quantity": 50,
     *   "notes": "Traslado por restock"
     * }
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->stockService->transferStock(
                $validated['product_id'],
                $validated['from_warehouse_id'],
                $validated['to_warehouse_id'],
                $validated['quantity'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Traslado realizado exitosamente',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al realizar el traslado',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ajuste de entrada de stock (incrementar)
     * POST /api/stock/adjustment/in
     *
     * Body:
     * {
     *   "product_id": 1,
     *   "warehouse_id": 1,
     *   "quantity": 100,
     *   "unit_cost": 15.50,
     *   "distribution_price": 18.00,  // OPCIONAL - si no se envía, usa unit_cost
     *   "reason": "manual_entry",
     *   "notes": "Corrección de inventario"
     * }
     */
    public function adjustmentIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'distribution_price' => 'nullable|numeric|min:0',
            'reason' => ['required', Rule::in(['purchase','manual_entry', 'found_stock', 'correction', 'return', 'other'])],
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->stockService->adjustmentIn(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['unit_cost'],
                $validated['reason'],
                $validated['notes'] ?? null,
                $validated['distribution_price'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Entrada de stock registrada exitosamente',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar entrada de stock',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ajuste de salida de stock (decrementar)
     * POST /api/stock/adjustment/out
     *
     * Body:
     * {
     *   "product_id": 1,
     *   "warehouse_id": 1,
     *   "quantity": 10,
     *   "reason": "damaged",
     *   "notes": "Productos dañados por humedad"
     * }
     */
    public function adjustmentOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|integer|min:1',
            'reason' => ['required', Rule::in(['sale','damaged', 'expired', 'lost', 'correction', 'sample', 'other'])],
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->stockService->adjustmentOut(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['reason'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Salida de stock registrada exitosamente',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar salida de stock',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Historial de movimientos de un producto
     * GET /api/stock/movements/product/{productId}
     */
    public function productMovements(int $productId, Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');
        $type = $request->input('type');
        $perPage = $request->input('per_page', 20);

        try {
            $movements = $this->stockService->getProductMovements(
                $productId,
                $warehouseId,
                $type,
                $perPage
            );

            return response()->json([
                'success' => true,
                'data' => $movements,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener movimientos',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ver lotes disponibles de un producto en un almacén
     * GET /api/stock/batches
     */
    public function availableBatches(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        try {
            $batches = $this->stockService->getAvailableBatches(
                $validated['product_id'],
                $validated['warehouse_id']
            );

            return response()->json([
                'success' => true,
                'data' => $batches,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener lotes',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sincronizar inventario con lotes
     * POST /api/stock/sync
     */
    public function syncInventory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        try {
            $result = $this->stockService->syncInventoryWithBatches(
                $validated['product_id'] ?? null,
                $validated['warehouse_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Sincronización completada',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
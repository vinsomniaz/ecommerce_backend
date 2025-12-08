<?php
// app/Http/Controllers/Api/StockManagementController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AdjustmentInRequest;
use App\Http\Requests\Stock\AdjustmentOutRequest;
use App\Http\Requests\Stock\TransferStockRequest;
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
     */
    public function transfer(TransferStockRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
     * ✅ SOLO unit_cost - el precio de venta se calcula automáticamente
     */
    public function adjustmentIn(AdjustmentInRequest $request): JsonResponse
    {
        // Validar en request AdjustmentInRequest
        $validated = $request->validated();

        try {
            $result = $this->stockService->adjustmentIn(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['unit_cost'],
                $validated['new_sale_price'],
                $validated['reason'],
                $validated['notes'] ?? null
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
     */
    public function adjustmentOut(AdjustmentOutRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

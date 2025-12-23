<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Http\Requests\Purchase\RegisterPaymentRequest;
use App\Services\PurchaseService;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Listar compras
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->purchaseService->getStatistics();
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar compras
     */
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'warehouse', 'user'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $purchases = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $purchases->items(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
                'last_page' => $purchases->lastPage()
            ]
        ]);
    }

    /**
     * Crear una nueva compra
     */
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        try {
            $purchase = $this->purchaseService->createPurchase($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada exitosamente',
                'data' => $purchase
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de compra
     */
    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(['details.product', 'batches', 'supplier', 'warehouse', 'user']);

        return response()->json([
            'success' => true,
            'data' => $purchase
        ]);
    }

    /**
     * Actualizar compra
     */
    public function update(UpdatePurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        try {
            $purchase = $this->purchaseService->updatePurchase($purchase, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Compra actualizada exitosamente',
                'data' => $purchase
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar compra
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        try {
            $this->purchaseService->deletePurchase($purchase);

            return response()->json([
                'success' => true,
                'message' => 'Compra eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar pago
     */
    public function registerPayment(RegisterPaymentRequest $request, Purchase $purchase): JsonResponse
    {
        try {
            $payment = $this->purchaseService->registerPayment($purchase, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

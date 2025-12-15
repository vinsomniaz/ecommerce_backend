<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
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

        return response()->json($purchases);
    }

    /**
     * Crear una nueva compra
     */
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        try {
            $purchase = $this->purchaseService->createPurchase($request->validated());

            return response()->json([
                'message' => 'Compra registrada exitosamente',
                'data' => $purchase
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
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

        return response()->json($purchase);
    }
}

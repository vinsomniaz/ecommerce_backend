<?php
// app/Http/Controllers/Api/ProductPriceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductPrices\StoreProductPriceRequest;
use App\Http\Requests\ProductPrices\UpdateProductPriceRequest;
use App\Http\Requests\ProductPrices\BulkUpdatePricesRequest;
use App\Http\Requests\ProductPrices\CalculatePriceRequest;
use App\Http\Requests\ProductPrices\CopyPricesRequest;
use App\Http\Resources\ProductPrices\ProductPriceResource;
use App\Services\ProductPriceService;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductPriceController extends Controller
{
    public function __construct(
        private ProductPriceService $priceService
    ) {}

    /**
     * Listar precios con filtros
     *
     * GET /api/product-prices
     * Filtros: product_id, price_list_id, warehouse_id, is_active, is_current
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'product_id',
            'price_list_id',
            'warehouse_id',
            'is_active',
            'sort_by',
            'sort_order'
        ]);

        $perPage = $request->input('per_page', 15);
        $prices = $this->priceService->getFiltered($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => ProductPriceResource::collection($prices),
            'meta' => [
                'current_page' => $prices->currentPage(),
                'last_page' => $prices->lastPage(),
                'per_page' => $prices->perPage(),
                'total' => $prices->total(),
            ],
        ]);
    }

    /**
     * Crear un nuevo precio
     *
     * POST /api/product-prices
     */
    public function store(StoreProductPriceRequest $request): JsonResponse
    {
        try {
            $price = $this->priceService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Precio creado exitosamente',
                'data' => new ProductPriceResource($price),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el precio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalle de un precio
     *
     * GET /api/product-prices/{id}
     */
    public function show(ProductPrice $productPrice): JsonResponse
    {
        $productPrice->load(['product', 'priceList', 'warehouse']);

        return response()->json([
            'success' => true,
            'data' => new ProductPriceResource($productPrice),
        ]);
    }

    /**
     * Actualizar un precio
     *
     * PUT/PATCH /api/product-prices/{id}
     */
    public function update(UpdateProductPriceRequest $request, ProductPrice $productPrice): JsonResponse
    {
        try {
            $updated = $this->priceService->update($productPrice, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Precio actualizado exitosamente',
                'data' => new ProductPriceResource($updated),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el precio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un precio
     *
     * DELETE /api/product-prices/{id}
     */
    public function destroy(ProductPrice $productPrice): JsonResponse
    {
        try {
            $this->priceService->delete($productPrice);

            return response()->json([
                'success' => true,
                'message' => 'Precio eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el precio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualización masiva de precios
     *
     * POST /api/product-prices/bulk-update
     *
     * Body:
     * {
     *   "product_ids": [1, 2, 3],
     *   "price_list_id": 1,
     *   "warehouse_id": null,
     *   "adjustment_type": "percentage", // percentage, fixed, replace
     *   "adjustment_value": 10,
     *   "apply_to_min_price": false
     * }
     */
    public function bulkUpdate(BulkUpdatePricesRequest $request): JsonResponse
    {
        try {
            $count = $this->priceService->bulkUpdate(
                $request->product_ids,
                $request->price_list_id,
                $request->warehouse_id,
                $request->adjustment_type,
                $request->adjustment_value,
                $request->apply_to_min_price
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} precios actualizados exitosamente",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la actualización masiva',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Copiar precios de una lista a otra
     *
     * POST /api/product-prices/copy
     *
     * Body:
     * {
     *   "source_price_list_id": 1,
     *   "target_price_list_id": 2,
     *   "product_ids": [1, 2, 3], // opcional
     *   "adjustment_percentage": 10 // opcional
     * }
     */
    public function copy(CopyPricesRequest $request): JsonResponse
    {
        try {
            $count = $this->priceService->copyPrices(
                $request->source_price_list_id,
                $request->target_price_list_id,
                $request->product_ids,
                $request->adjustment_percentage
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} precios copiados exitosamente",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al copiar precios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calcular precio sugerido basado en margen
     *
     * POST /api/product-prices/calculate
     *
     * Body:
     * {
     *   "product_id": 1,
     *   "margin_percentage": 25,
     *   "base_cost": 100 // opcional, usa average_cost si no se especifica
     * }
     */
    public function calculate(CalculatePriceRequest $request): JsonResponse
    {
        try {
            $result = $this->priceService->calculateSuggestedPrice(
                $request->product_id,
                $request->margin_percentage,
                $request->base_cost
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtener precios de un producto específico
     *
     * GET /api/product-prices/by-product/{productId}
     */
    public function byProduct(int $productId, Request $request): JsonResponse
    {
        $query = ProductPrice::where('product_id', $productId)
            ->with(['priceList', 'warehouse']);

        // Filtrar por lista de precios si se especifica
        if ($request->filled('price_list_id')) {
            $query->where('price_list_id', $request->price_list_id);
        }

        // Filtrar solo precios activos si se solicita
        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }

        $prices = $query->orderBy('price_list_id')
            ->orderBy('min_quantity')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductPriceResource::collection($prices),
            'count' => $prices->count(),
        ]);
    }

    /**
     * Estadísticas de precios
     *
     * GET /api/product-prices/statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->priceService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Desactivar precios expirados
     *
     * POST /api/product-prices/deactivate-expired
     */
    public function deactivateExpired(): JsonResponse
    {
        try {
            $count = $this->priceService->deactivateExpiredPrices();

            return response()->json([
                'success' => true,
                'message' => "{$count} precios expirados desactivados",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar precios expirados',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar/desactivar un precio
     *
     * PATCH /api/product-prices/{id}/toggle-active
     */
    public function toggleActive(ProductPrice $productPrice): JsonResponse
    {
        try {
            $productPrice->update([
                'is_active' => !$productPrice->is_active
            ]);

            $status = $productPrice->is_active ? 'activado' : 'desactivado';

            return response()->json([
                'success' => true,
                'message' => "Precio {$status} exitosamente",
                'data' => new ProductPriceResource($productPrice->fresh(['product', 'priceList', 'warehouse'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado del precio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

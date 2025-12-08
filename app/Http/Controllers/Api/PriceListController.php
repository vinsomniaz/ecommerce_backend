<?php
// app/Http/Controllers/Api/PriceListController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PriceLists\StorePriceListRequest;
use App\Http\Requests\PriceLists\UpdatePriceListRequest;
use App\Http\Resources\PriceLists\PriceListResource;
use App\Http\Resources\PriceLists\PriceListCollection;
use App\Services\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    protected PriceListService $priceListService;

    public function __construct(PriceListService $priceListService)
    {
        $this->priceListService = $priceListService;
    }

    /**
     * Listar todas las listas de precios
     * GET /api/price-lists
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $isActive = $request->input('is_active');

            $priceLists = $this->priceListService->getAllPriceLists(
                $perPage,
                $search,
                $isActive
            );

            return response()->json([
                'success' => true,
                'message' => 'Listas de precios obtenidas correctamente',
                'data' => $priceLists->items(),
                'meta' => [
                    'current_page' => $priceLists->currentPage(),
                    'from' => $priceLists->firstItem(),
                    'last_page' => $priceLists->lastPage(),
                    'per_page' => $priceLists->perPage(),
                    'to' => $priceLists->lastItem(),
                    'total' => $priceLists->total(),
                ],
                'links' => [
                    'first' => $priceLists->url(1),
                    'last' => $priceLists->url($priceLists->lastPage()),
                    'prev' => $priceLists->previousPageUrl(),
                    'next' => $priceLists->nextPageUrl(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las listas de precios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener solo listas de precios activas (para selects)
     * GET /api/price-lists/active
     */
    public function active(): JsonResponse
    {
        try {
            $priceLists = $this->priceListService->getActivePriceLists();

            return response()->json([
                'success' => true,
                'message' => 'Listas de precios activas obtenidas',
                'data' => PriceListResource::collection($priceLists),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener listas activas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de listas de precios
     * GET /api/price-lists/statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->priceListService->getStatistics();

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas obtenidas correctamente',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear una nueva lista de precios
     * POST /api/price-lists
     */
    public function store(StorePriceListRequest $request): JsonResponse
    {
        try {
            $priceList = $this->priceListService->createPriceList($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Lista de precios creada exitosamente',
                'data' => new PriceListResource($priceList),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la lista de precios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener una lista de precios específica
     * GET /api/price-lists/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $priceList = $this->priceListService->getPriceListById($id);

            if (!$priceList) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lista de precios no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lista de precios obtenida correctamente',
                'data' => new PriceListResource($priceList),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la lista de precios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una lista de precios
     * PUT/PATCH /api/price-lists/{id}
     */
    public function update(UpdatePriceListRequest $request, int $id): JsonResponse
    {
        try {
            $priceList = $this->priceListService->updatePriceList($id, $request->validated());

            if (!$priceList) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lista de precios no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lista de precios actualizada exitosamente',
                'data' => new PriceListResource($priceList),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la lista de precios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una lista de precios
     * DELETE /api/price-lists/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->priceListService->deletePriceList($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['code'] ?? 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lista de precios eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la lista de precios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar/Desactivar lista de precios
     * PATCH /api/price-lists/{id}/toggle-status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $priceList = $this->priceListService->toggleStatus($id);

            if (!$priceList) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lista de precios no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $priceList->is_active
                    ? 'Lista de precios activada'
                    : 'Lista de precios desactivada',
                'data' => new PriceListResource($priceList),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener productos con precios de una lista específica
     * GET /api/price-lists/{id}/products
     */
    public function products(int $id, Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $products = $this->priceListService->getProductsWithPrices($id, $perPage);

            if (!$products) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lista de precios no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Productos con precios obtenidos correctamente',
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'from' => $products->firstItem(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'to' => $products->lastItem(),
                    'total' => $products->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php
// app/Http/Controllers/Api/ProductController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Products\EcommerceProductResource;
use App\Http\Resources\Categories\EcommerceCategoryResource;
use App\Services\EcommerceService;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\CategoryService;
use App\Models\Category;
use App\Models\ExchangeRate;

class EcommerceController extends Controller
{
    public function __construct(
        private EcommerceService $ecommerceservice,
        private CategoryService $categoryService
    ) {}

    /**
     * Listar productos
     */
    public function index(Request $request)
    {
        $currency = $request->query('currency');
        $exchangeRateFactor = $this->getExchangeRateFactor($currency);

        if ($exchangeRateFactor !== null && $exchangeRateFactor !== 1.0) {
            // Inyectar el factor al Request para que ProductResource lo use
            $request->merge(['exchange_rate_factor' => $exchangeRateFactor]);
        }

        $filters = $request->only([
            'search',
            'category_id',
            'brand',
            'is_active',
            'is_featured',
            'visible_online',
            'is_new',           // ✅ AGREGAR ESTA LÍNEA
            'warehouse_id',
            'with_stock',
            'low_stock',
            'sort_by',
            'sort_order',
            'with_trashed',
            'min_price',
            'max_price'
        ]);

        $perPage = $request->input('per_page', 15);
        $products = $this->ecommerceservice->getFiltered($filters, $perPage);

        return EcommerceProductResource::collection($products);
    }
    /**
     * Crear un nuevo producto (SIN precios - se asignarán con compras)
     */


    /**
     * Ver detalles de un producto
     */
    public function show(Product $product, Request $request): JsonResponse
    {
        $product->load(['media', 'category', 'attributes']);

        return response()->json([
            'success' => true,
            'data' => new EcommerceProductResource($product),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS DE CATEGORÍAS (PÚBLICOS)
    |--------------------------------------------------------------------------
    */

    /**
     * Listar todas las categorías (Público)
     * Lógica movida desde CategoryController@index
     */
    public function listCategories(Request $request): JsonResponse
    {
        // Solo permitir filtros públicos
        $publicRequest = new Request($request->only([
            'per_page',
            'search',
            'level',
            'parent_id'
        ]));

        // Forzar que solo se muestren activas
        $publicRequest->merge(['is_active' => true]);

        $categories = $this->categoryService->getCategories($publicRequest);

        // Respuesta cuando no hay categorías
        if ($categories->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No se encontraron categorías',
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $publicRequest->query('per_page', 20),
                    'current_page' => 1,
                    'last_page' => 1,
                ]
            ], 200);
        }

        // Respuesta exitosa con Resource
        return response()->json([
            'success' => true,
            'message' => 'Categorías obtenidas correctamente',
            'data' => EcommerceCategoryResource::collection($categories->items()),
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ]
        ], 200);
    }

    /**
     * Endpoint para Lista de Distribución (Sin paginación)
     */
    public function distributionList(Request $request)
    {
        $filters = $request->only([
            'search',
            'category_id',
            'sort_by',
            'sort_order'
        ]);

        // Ya no enviamos per_page
        $products = $this->ecommerceservice->getDistributionList($filters);

        return EcommerceProductResource::collection($products);
    }

    /**
     * Mostrar una categoría específica (Público)
     * Lógica movida desde CategoryController@show
     * Nota: CategoryService->getCategoryById($id) debe lanzar
     * CategoryNotFoundException si no la encuentra.
     */
    public function showCategory(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        // Asegurarse que solo se muestren categorías activas al público
        if (!$category->is_active) {
            // Podrías lanzar una excepción personalizada que el Handler capture como 404
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categoría obtenida correctamente',
            'data' => new EcommerceCategoryResource($category)
        ], 200);
    }

    /**
     * Obtener árbol jerárquico de categorías (Público)
     * Lógica movida desde CategoryController@tree
     */
    public function getCategoryTree(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->with('children.children')
            ->where('is_active', true) // <-- Importante: solo activas
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Árbol de categorías obtenido correctamente',
            'data' => EcommerceCategoryResource::collection($categories)
        ], 200);
    }

    /**
     * NUEVO: Obtiene el factor de tipo de cambio de la BD
     */
    private function getExchangeRateFactor(?string $currency): ?float
    {
        if (empty($currency) || strtoupper($currency) === 'PEN') {
            return 1.0;
        }

        return ExchangeRate::getRate($currency);
    }
}

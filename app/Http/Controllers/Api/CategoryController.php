<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\Categories\CategoryCollection;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {}

    /**
     * Listar todas las categorías con filtros
     *
     * @group Categories
     * @queryParam per_page int Cantidad por página. Default: 20
     * @queryParam search string Buscar por nombre, descripción o slug
     * @queryParam level int Filtrar por nivel (1, 2, 3)
     * @queryParam parent_id int Filtrar por categoría padre (0 para raíz)
     * @queryParam is_active boolean Filtrar por estado (true/false)
     */
    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getCategories($request);

        if ($categories->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aún no se ha creado ninguna categoría',
                'data' => [],
                'meta' => [
                    'pagination' => [
                        'total' => 0,
                        'per_page' => $request->query('per_page', 20),
                        'current_page' => 1,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                    ],
                    'stats' => [
                        'total_categories' => Category::count(),
                        'active_categories' => Category::active()->count(),
                        'level1_categories' => Category::root()->count(),
                        'products_with_category' => Product::whereNotNull('category_id')->count(),
                    ]
                ]
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categorías obtenidas correctamente',
            'data' => (new CategoryCollection($categories))->toArray($request)['data'],
            'meta' => (new CategoryCollection($categories))->with($request)['meta'],
        ], 200);
    }


    /**
     * Mostrar una categoría específica
     *
     * @group Categories
     *
     * Las excepciones se manejan automáticamente:
     * - CategoryNotFoundException (404)
     */
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        return response()->json([
            'success' => true,
            'message' => 'Categoría obtenida correctamente',
            'data' => new CategoryResource($category)
        ], 200);
    }

    /**
     * Crear nueva categoría
     *
     * @group Categories
     *
     * Las excepciones se manejan automáticamente:
     * - CategoryValidationException (422)
     * - CategoryMaxLevelException (422)
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->createCategory($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada correctamente',
            'data' => new CategoryResource($category)
        ], 201);
    }

    /**
     * Actualizar categoría existente
     *
     * @group Categories
     *
     * Las excepciones se manejan automáticamente:
     * - CategoryNotFoundException (404)
     * - CategoryValidationException (422)
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->updateCategory($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada correctamente',
            'data' => new CategoryResource($category)
        ], 200);
    }

    /**
     * Eliminar categoría
     *
     * @group Categories
     *
     * Las excepciones se manejan automáticamente:
     * - CategoryNotFoundException (404)
     * - CategoryHasChildrenException (409)
     */
    public function destroy(int $id): JsonResponse
    {
        $this->categoryService->deleteCategory($id);

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada correctamente'
        ], 200);
    }

    /**
     * Obtener árbol jerárquico de categorías
     *
     * @group Categories
     */
    public function tree(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->with('children.children')
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Árbol de categorías obtenido correctamente',
            'data' => CategoryResource::collection($categories)
        ], 200);
    }
}

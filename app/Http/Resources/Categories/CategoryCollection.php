<?php

namespace App\Http\Resources\Categories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Category;
use App\Models\Product;

class CategoryCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => CategoryResource::collection($this->collection),
        ];
    }

    /**
     * Evita que Laravel agregue meta/links duplicados automáticamente.
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }

    /**
     * Data adicional global (paginación + estadísticas).
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),

                'stats' => [
                    // ✅ Todas las categorías
                    'total_categories' => Category::count(),

                    // ✅ Categorías activas
                    'active_categories' => Category::active()->count(),

                    // ✅ Cantidad nivel 1 (root)
                    'level1_categories' => Category::root()->count(),

                    // ✅ Total productos asociados a alguna categoría
                    'products_with_category' => Product::whereNotNull('category_id')->count(),
                ],
            ],
        ];
    }
}

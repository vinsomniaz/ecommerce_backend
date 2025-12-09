<?php
// app/Http/Resources/Products/ProductCollection.php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Product;

class ProductCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * Customize the pagination information for the resource.
     *
     * Este método PREVIENE que Laravel agregue automáticamente
     * los metadatos de paginación duplicados.
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        // Retornar array vacío para que Laravel NO agregue meta automáticamente
        return [];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                // Información de paginación
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),

                // Estadísticas de la página actual
                'page_stats' => [
                    'active_products' => $this->collection->where('is_active', true)->count(),
                    'inactive_products' => $this->collection->where('is_active', false)->count(),
                    'featured_products' => $this->collection->where('is_featured', true)->count(),
                    'total_stock_in_page' => $this->collection->sum('total_stock'),
                ],

                // Estadísticas globales
                'global_stats' => [
                    'total_products' => Product::count(),
                    'active_products' => Product::where('is_active', true)->count(),
                    'inactive_products' => Product::where('is_active', false)->count(),
                    'featured_products' => Product::where('is_featured', true)->count(),
                    'online_products' => Product::where('visible_online', true)->count(),
                    'new_products' => Product::where('is_new', true)->count(),
                ],
            ],
        ];
    }
}

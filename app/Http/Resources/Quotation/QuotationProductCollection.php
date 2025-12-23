<?php

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class QuotationProductCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     */
    public $collects = QuotationProductResource::class;

    /**
     * Additional data to include in response
     */
    private array $filters = [];
    private array $globalStats = [];

    /**
     * Set applied filters for response
     */
    public function setFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Set global stats for response
     */
    public function setGlobalStats(array $stats): self
    {
        $this->globalStats = $stats;
        return $this;
    }

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
     * Customize the pagination information.
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                // Paginación
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
                
                // Filtros aplicados
                'applied_filters' => $this->filters,
                
                // Estadísticas de la página
                'page_stats' => [
                    'products_with_stock' => $this->collection->filter(fn($p) => 
                        ($p->warehouse_stock['available_stock'] ?? 0) > 0
                    )->count(),
                    'products_without_stock' => $this->collection->filter(fn($p) => 
                        ($p->warehouse_stock['available_stock'] ?? 0) === 0
                    )->count(),
                ],
                
                // Estadísticas globales
                'global_stats' => $this->globalStats,
            ],
        ];
    }
}

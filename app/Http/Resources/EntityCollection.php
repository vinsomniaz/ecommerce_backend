<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Services\EntityService;

class EntityCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => EntityResource::collection($this->collection),
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
                    'active_entities' => $this->collection->where('is_active', true)->count(),
                    'inactive_entities' => $this->collection->where('is_active', false)->count(),
                    'customers_in_page' => $this->collection->filter(fn($e) => in_array($e->type, ['customer', 'both']))->count(),
                    'suppliers_in_page' => $this->collection->filter(fn($e) => in_array($e->type, ['supplier', 'both']))->count(),
                ],

                // Estadísticas globales
                'global_stats' => app(EntityService::class)->getGlobalStatistics(),
            ],
        ];
    }
}

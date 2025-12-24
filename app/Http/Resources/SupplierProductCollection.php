<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\Cache;

class SupplierProductCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => SupplierProductResource::collection($this->collection),
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
        $supplierId = $request->supplier_id;

        // Estadísticas calculadas en tiempo real (sin cache)
        // Universo: sin product_id, sin supplier_category, con category_suggested
        $query = SupplierProduct::query()
            ->whereNull('product_id')
            ->whereNull('supplier_category')
            ->whereNotNull('category_suggested');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $total = $query->count();
        $mapped = (clone $query)->whereNotNull('category_id')->count();
        $unmapped = (clone $query)->whereNull('category_id')->count();
        $active = (clone $query)->where('is_active', true)->count();
        $inactive = (clone $query)->where('is_active', false)->count();

        $stats = [
            'total' => $total,
            'mapped' => $mapped,
            'unmapped' => $unmapped,
            'active' => $active,
            'inactive' => $inactive,
            'mapping_rate' => $total > 0 ? round(($mapped / $total) * 100, 2) : 0,
        ];

        return [
            'meta' => [
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
                'stats' => $stats,
            ],
        ];
    }
}

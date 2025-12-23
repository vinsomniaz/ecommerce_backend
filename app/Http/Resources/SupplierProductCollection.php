<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupplierProductCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],
            'statistics' => $this->when($request->boolean('include_stats'), function () {
                return [
                    'total_products' => $this->collection->count(),
                    'available' => $this->collection->where('is_available', true)->count(),
                    'active' => $this->collection->where('is_active', true)->count(),
                    'with_stock' => $this->collection->where('available_stock', '>', 0)->count(),
                    'average_price' => $this->collection->avg('purchase_price'),
                    'total_stock' => $this->collection->sum('available_stock'),
                ];
            }),
        ];
    }
}

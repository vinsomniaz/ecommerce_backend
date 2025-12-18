<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupplierImportCollection extends ResourceCollection
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
                    'total_imports' => $this->collection->count(),
                    'completed' => $this->collection->where('status', 'completed')->count(),
                    'failed' => $this->collection->where('status', 'failed')->count(),
                    'pending' => $this->collection->where('status', 'pending')->count(),
                    'total_products' => $this->collection->sum('total_products'),
                ];
            }),
        ];
    }
}

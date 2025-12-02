<?php
// app/Http/Resources/Products/ProductCollection.php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'active_products' => $this->collection->where('is_active', true)->count(),
                'featured_products' => $this->collection->where('is_featured', true)->count(),
                'total_stock' => $this->collection->sum('total_stock'),
                'average_cost_total' => $this->collection->sum('average_cost'),
            ],
        ];
    }
}

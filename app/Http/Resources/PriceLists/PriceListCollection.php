<?php
// app/Http/Resources/PriceLists/PriceListCollection.php

namespace App\Http\Resources\PriceLists;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PriceListCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
            ],
        ];
    }
}

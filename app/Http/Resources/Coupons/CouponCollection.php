<?php

namespace App\Http\Resources\Coupons;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Services\CouponService;

class CouponCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => CouponResource::collection($this->collection),
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

                'stats' => app(CouponService::class)->getGlobalStats(),
            ],
        ];
    }
}

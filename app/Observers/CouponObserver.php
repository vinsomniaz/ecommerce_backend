<?php

namespace App\Observers;

use App\Models\Coupon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CouponObserver implements ShouldHandleEventsAfterCommit
{
    public bool $afterCommit = true;

    public function created(Coupon $coupon): void
    {
        $this->flush($coupon);
    }

    public function updated(Coupon $coupon): void
    {
        $this->flush($coupon);
    }

    public function deleted(Coupon $coupon): void
    {
        $this->flush($coupon);
    }

    private function flush(Coupon $coupon): void
    {
        // Cache individual del cupón
        Cache::forget("coupon_{$coupon->id}");

        // Versionado de listados paginados
        Cache::increment('coupons_version');

        // Stats globales
        Cache::forget('coupons_global_stats');
    }
}

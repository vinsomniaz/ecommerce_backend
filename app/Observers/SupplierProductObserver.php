<?php

namespace App\Observers;

use App\Models\SupplierProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SupplierProductObserver implements ShouldHandleEventsAfterCommit
{
    public bool $afterCommit = true;

    public function created(SupplierProduct $product): void
    {
        $this->flush($product);
    }

    public function updated(SupplierProduct $product): void
    {
        $this->flush($product);
    }

    public function deleted(SupplierProduct $product): void
    {
        $this->flush($product);
    }

    private function flush(SupplierProduct $product): void
    {
        // Cache individual del producto
        Cache::forget("supplier_product_{$product->id}");

        // Invalidar caches relacionados con el proveedor
        if ($product->supplier_id) {
            Cache::forget("supplier_{$product->supplier_id}_products_stats");
        }

        // Invalidar versión global para forzar recarga de listados paginados
        Cache::increment('supplier_products_version');

        // Invalidar estadísticas por proveedor
        Cache::forget("supplier_products_stats_v" . Cache::get('supplier_products_version', 1) . "_" . ($product->supplier_id ?? 'all'));
        Cache::forget("supplier_products_stats_v" . Cache::get('supplier_products_version', 1) . "_all");
    }
}

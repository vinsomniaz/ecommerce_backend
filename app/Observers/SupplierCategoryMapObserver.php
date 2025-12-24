<?php

namespace App\Observers;

use App\Models\SupplierCategoryMap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SupplierCategoryMapObserver implements ShouldHandleEventsAfterCommit
{
    public bool $afterCommit = true;

    public function created(SupplierCategoryMap $map): void
    {
        $this->flush($map);
    }

    public function updated(SupplierCategoryMap $map): void
    {
        $this->flush($map);
    }

    public function deleted(SupplierCategoryMap $map): void
    {
        $this->flush($map);
    }

    private function flush(SupplierCategoryMap $map): void
    {
        // Cache individual del mapeo
        Cache::forget("supplier_category_map_{$map->id}");

        // Invalidar caches relacionados con el proveedor
        if ($map->supplier_id) {
            Cache::forget("supplier_{$map->supplier_id}_category_maps");
        }

        // Invalidar versión global para forzar recarga de listados paginados
        Cache::increment('supplier_category_maps_version');

        // Invalidar estadísticas por proveedor
        Cache::forget("supplier_category_maps_stats_v" . Cache::get('supplier_category_maps_version', 1) . "_" . ($map->supplier_id ?? 'all'));
        Cache::forget("supplier_category_maps_stats_v" . Cache::get('supplier_category_maps_version', 1) . "_all");
    }
}

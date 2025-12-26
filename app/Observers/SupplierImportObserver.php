<?php

namespace App\Observers;

use App\Models\SupplierImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SupplierImportObserver implements ShouldHandleEventsAfterCommit
{
    public bool $afterCommit = true;

    public function created(SupplierImport $import): void
    {
        $this->flush($import);
    }

    public function updated(SupplierImport $import): void
    {
        $this->flush($import);
    }

    public function deleted(SupplierImport $import): void
    {
        $this->flush($import);
    }

    private function flush(SupplierImport $import): void
    {
        // Cache individual de la importación
        Cache::forget("supplier_import_{$import->id}");

        // Invalidar caches relacionados con el proveedor
        if ($import->supplier_id) {
            Cache::forget("supplier_{$import->supplier_id}_imports_stats");
        }

        // Invalidar versión global para forzar recarga de listados paginados
        Cache::increment('supplier_imports_version');

        // Invalidar estadísticas por proveedor
        Cache::forget("supplier_imports_stats_v" . Cache::get('supplier_imports_version', 1) . "_" . ($import->supplier_id ?? 'all'));
        Cache::forget("supplier_imports_stats_v" . Cache::get('supplier_imports_version', 1) . "_all");
    }
}

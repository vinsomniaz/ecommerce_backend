<?php

namespace App\Services;

use App\Models\SupplierImport;
use App\Models\SupplierProduct;
use App\Models\SupplierCategoryMap;
use App\Models\Entity;
use App\Jobs\ProcessSupplierImportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SupplierImportService
{
    /**
     * Procesa un payload de sincronización del scraper
     */
    public function processSync(array $payload): array
    {
        $supplierId = $payload['supplier_id'];
        $hash = $payload['hash'];

        // Idempotencia: Verificar imports recientes (últimos 5 minutos) para evitar duplicados
        $recentImport = SupplierImport::where('supplier_id', $supplierId)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($recentImport) {
            return [
                'changed' => false,
                'status' => 202,
                'message' => 'Import ya en proceso',
                'import_id' => $recentImport->id,
            ];
        }

        // Verificar si ya existe una importación con el mismo hash
        $existingImport = SupplierImport::where('supplier_id', $supplierId)
            ->where('raw_data->hash', $hash)
            ->where('status', 'completed')
            ->first();

        if ($existingImport) {
            return [
                'changed' => false,
                'status' => 204,
                'message' => 'Sin cambios detectados',
                'import_id' => $existingImport->id,
            ];
        }

        // Crear registro de importación
        $import = $this->createImport($payload);

        // Procesar items inmediatamente (síncrono) o en background
        if (count($payload['items']) < 100) {
            // Procesar síncronamente si son pocos productos
            $this->processImport($import);
            $import->refresh();

            return [
                'changed' => true,
                'status' => 200,
                'message' => 'Importación procesada exitosamente',
                'import_id' => $import->id,
                'stats' => [
                    'total' => $import->total_products,
                    'processed' => $import->processed_products,
                    'new' => $import->new_products,
                    'updated' => $import->updated_products,
                ],
            ];
        }

        // Procesar en background si son muchos productos
        ProcessSupplierImportJob::dispatch($import->id);

        return [
            'changed' => true,
            'status' => 202,
            'message' => 'Importación encolada para procesamiento',
            'import_id' => $import->id,
        ];
    }

    /**
     * Crea un registro de importación
     */
    protected function createImport(array $payload): SupplierImport
    {
        return SupplierImport::create([
            'supplier_id' => $payload['supplier_id'],
            'raw_data' => $payload, // Guarda todo el payload
            'fetched_at' => $payload['fetched_at'],
            'margin_percent' => $payload['margin_percent'] ?? null,
            'source_totals' => $payload['source_totals'] ?? null,
            'items_count' => count($payload['items']),
            'status' => 'pending',
            'total_products' => count($payload['items']),
        ]);
    }

    /**
     * Procesa una importación
     */
    public function processImport(SupplierImport $import): void
    {
        $import->update(['status' => 'processing']);

        try {
            $payload = is_string($import->raw_data)
                ? json_decode($import->raw_data, true)
                : $import->raw_data;

            $items = $payload['items'] ?? [];
            $stats = [
                'processed' => 0,
                'new' => 0,
                'updated' => 0,
            ];

            DB::beginTransaction();

            // Paso 1: Procesar categorías
            $this->processCategoryMaps($import, $items);

            // Paso 2: Procesar productos
            foreach ($items as $item) {
                $wasNew = $this->upsertSupplierProduct($import, $item);

                $stats['processed']++;
                if ($wasNew) {
                    $stats['new']++;
                } else {
                    $stats['updated']++;
                }
            }

            DB::commit();

            $import->update([
                'status' => 'completed',
                'processed_products' => $stats['processed'],
                'new_products' => $stats['new'],
                'updated_products' => $stats['updated'],
                'processed_at' => now(),
            ]);

            // Limpiar cache relacionado
            $this->clearSupplierCache($import->supplier_id);
        } catch (\Exception $e) {
            DB::rollBack();

            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('Error procesando importación de proveedor', [
                'import_id' => $import->id,
                'supplier_id' => $import->supplier_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Crea o actualiza un producto de proveedor
     */
    protected function upsertSupplierProduct(SupplierImport $import, array $item): bool
    {
        $supplierProduct = SupplierProduct::updateOrCreate(
            [
                'supplier_id' => $import->supplier_id,
                'supplier_sku' => $item['supplier_sku'],
            ],
            [
                'supplier_name' => $item['name'] ?? null,
                'brand' => $item['brand'] ?? null,
                'location' => $item['location'] ?? null,
                'source_url' => $item['url'] ?? null,
                'image_url' => $item['image_url'] ?? null,
                'supplier_category' => $item['supplier_category'] ?? null,
                'category_suggested' => $item['category_suggested'] ?? null,
                'purchase_price' => $item['supplier_price'] ?? null,
                'sale_price' => $item['price_suggested'] ?? null,
                'currency' => $item['currency'] ?? 'PEN',
                'available_stock' => $item['stock_qty'] ?? 0, // is_available se calcula automáticamente
                'stock_text' => $item['stock_text'] ?? null,
                'last_seen_at' => now(),
                'last_import_id' => $import->id,
                'price_updated_at' => now(),
            ]
        );

        return $supplierProduct->wasRecentlyCreated;
    }

    /**
     * Procesa mapeos de categorías
     */
    protected function processCategoryMaps(SupplierImport $import, array $items): void
    {
        // Extraer categorías únicas del payload
        $categories = collect($items)
            ->pluck('supplier_category')
            ->filter()
            ->unique()
            ->values();

        foreach ($categories as $categoryKey) {
            SupplierCategoryMap::updateOrCreate(
                [
                    'supplier_id' => $import->supplier_id,
                    'supplier_category' => $categoryKey,
                ],
                [
                    // No sobrescribir category_id si ya existe mapeo manual
                    // Solo actualizar last_seen_at y activar
                    'is_active' => true,
                ]
            );
        }

        // Desactivar categorías que ya no aparecen
        SupplierCategoryMap::where('supplier_id', $import->supplier_id)
            ->whereNotIn('supplier_category', $categories->toArray())
            ->update(['is_active' => false]);
    }

    /**
     * Limpia el cache relacionado al proveedor
     */
    protected function clearSupplierCache(int $supplierId): void
    {
        Cache::tags(['supplier_products', "supplier_{$supplierId}"])->flush();
    }

    /**
     * Obtiene estadísticas de importaciones
     */
    public function getStatistics(?int $supplierId = null): array
    {
        $query = SupplierImport::query();

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return [
            'total_imports' => $query->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'total_products_imported' => $query->sum('total_products'),
            'total_products_processed' => $query->sum('processed_products'),
            'last_import' => $query->latest()->first()?->created_at,
        ];
    }

    /**
     * Reintenta una importación fallida
     */
    public function retryImport(SupplierImport $import): void
    {
        if ($import->status !== 'failed') {
            throw new \Exception('Solo se pueden reintentar importaciones fallidas');
        }

        $import->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        ProcessSupplierImportJob::dispatch($import->id);
    }
}

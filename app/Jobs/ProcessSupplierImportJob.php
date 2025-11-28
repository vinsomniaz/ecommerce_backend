<?php

namespace App\Jobs;

use App\Models\SupplierImport;
use App\Models\SupplierProduct;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSupplierImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $importId
    ) {}

    public function handle(): void
    {
        $import = SupplierImport::find($this->importId);

        if (!$import) {
            return;
        }

        $import->update(['status' => 'processing']);

        try {
            $products = json_decode($import->raw_data, true);
            $processed = 0;
            $updated = 0;
            $new = 0;

            foreach ($products as $item) {
                // Buscar producto por SKU del proveedor
                $product = $this->findProductBySku($item['supplier_sku'], $import->supplier_id);

                if ($product) {
                    $supplierProduct = SupplierProduct::updateOrCreate(
                        [
                            'supplier_id' => $import->supplier_id,
                            'product_id' => $product->id,
                        ],
                        [
                            'supplier_sku' => $item['supplier_sku'],
                            'purchase_price' => $item['price'],
                            'currency' => $item['currency'] ?? 'PEN',
                            'available_stock' => $item['stock'] ?? 0,
                            'is_available' => ($item['stock'] ?? 0) > 0,
                            'price_updated_at' => now(),
                        ]
                    );

                    if ($supplierProduct->wasRecentlyCreated) {
                        $new++;
                    } else {
                        $updated++;
                    }
                }

                $processed++;
            }

            $import->update([
                'status' => 'completed',
                'processed_products' => $processed,
                'updated_products' => $updated,
                'new_products' => $new,
                'processed_at' => now(),
            ]);

        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    private function findProductBySku(string $supplierSku, int $supplierId): ?Product
    {
        // Intentar mapeo directo primero (campos deltron_sku, intcomex_sku, etc.)
        $supplier = \App\Models\Entity::find($supplierId);
        $supplierName = strtolower($supplier->trade_name ?? '');

        if (str_contains($supplierName, 'deltron')) {
            $product = Product::where('deltron_sku', $supplierSku)->first();
            if ($product) return $product;
        }

        if (str_contains($supplierName, 'intcomex')) {
            $product = Product::where('intcomex_sku', $supplierSku)->first();
            if ($product) return $product;
        }

        if (str_contains($supplierName, 'cva')) {
            $product = Product::where('cva_sku', $supplierSku)->first();
            if ($product) return $product;
        }

        // Si no hay mapeo directo, buscar en product_supplier_codes (si existe)
        // TODO: Implementar cuando crees la tabla

        return null;
    }
}
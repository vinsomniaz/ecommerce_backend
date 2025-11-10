<?php
// app/Services/StockManagementService.php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Supports\PurchaseBatch;
use App\Models\Supports\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class StockManagementService
{
    /**
     * Traslado de stock entre almacenes (con FIFO)
     */
    public function transferStock(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $notes) {

            // Validar almacenes
            $fromWarehouse = Warehouse::findOrFail($fromWarehouseId);
            $toWarehouse = Warehouse::findOrFail($toWarehouseId);
            $product = Product::findOrFail($productId);

            // Verificar stock disponible en origen
            $inventoryFrom = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->first();

            if (!$inventoryFrom || $inventoryFrom->available_stock < $quantity) {
                throw new \Exception(
                    "Stock insuficiente en {$fromWarehouse->name}. " .
                    "Disponible: " . ($inventoryFrom->available_stock ?? 0) .
                    ", Requerido: {$quantity}"
                );
            }

            $remainingQty = $quantity;
            $transferredBatches = [];

            // Obtener lotes disponibles del almacén origen (FIFO)
            $batches = PurchaseBatch::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->orderBy('purchase_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($batches->sum('quantity_available') < $quantity) {
                throw new \Exception("Los lotes activos no cubren la cantidad solicitada");
            }

            foreach ($batches as $batch) {
                if ($remainingQty <= 0) break;

                $qtyToTransfer = min($remainingQty, $batch->quantity_available);

                // 1. Descontar del lote origen
                $batch->quantity_available -= $qtyToTransfer;
                if ($batch->quantity_available == 0) {
                    $batch->status = 'depleted';
                }
                $batch->save();

                // 2. Crear nuevo lote en destino (manteniendo referencia al purchase original)
                $newBatch = PurchaseBatch::create([
                    'purchase_id' => $batch->purchase_id,
                    'product_id' => $productId,
                    'warehouse_id' => $toWarehouseId,
                    'batch_code' => $batch->batch_code . '-T' . now()->timestamp,
                    'quantity_purchased' => $qtyToTransfer,
                    'quantity_available' => $qtyToTransfer,
                    'purchase_price' => $batch->purchase_price,
                    'distribution_price' => $batch->distribution_price,
                    'purchase_date' => $batch->purchase_date,
                    'expiry_date' => $batch->expiry_date,
                    'status' => 'active',
                    'notes' => "Trasladado desde {$fromWarehouse->name}",
                ]);

                // 3. Registrar salida del almacén origen
                StockMovement::create([
                    'product_id' => $productId,
                    'warehouse_id' => $fromWarehouseId,
                    'purchase_batch_id' => $batch->id,
                    'type' => 'transfer',
                    'quantity' => -$qtyToTransfer,
                    'unit_cost' => $batch->distribution_price,
                    'reference_type' => 'transfer_out',
                    'reference_id' => $toWarehouseId,
                    'user_id' => auth()->id(),
                    'notes' => $notes ?? "Traslado a {$toWarehouse->name}",
                    'moved_at' => now(),
                ]);

                // 4. Registrar entrada al almacén destino
                StockMovement::create([
                    'product_id' => $productId,
                    'warehouse_id' => $toWarehouseId,
                    'purchase_batch_id' => $newBatch->id,
                    'type' => 'transfer',
                    'quantity' => $qtyToTransfer,
                    'unit_cost' => $batch->distribution_price,
                    'reference_type' => 'transfer_in',
                    'reference_id' => $fromWarehouseId,
                    'user_id' => auth()->id(),
                    'notes' => $notes ?? "Traslado desde {$fromWarehouse->name}",
                    'moved_at' => now(),
                ]);

                $remainingQty -= $qtyToTransfer;
                $transferredBatches[] = [
                    'batch_code' => $newBatch->batch_code,
                    'quantity' => $qtyToTransfer,
                ];
            }

            // 5. Actualizar inventario origen
            $inventoryFrom->available_stock -= $quantity;
            $inventoryFrom->last_movement_at = now();
            $inventoryFrom->save();

            // 6. Actualizar/crear inventario destino
            $inventoryTo = Inventory::firstOrCreate(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $toWarehouseId,
                ],
                [
                    'available_stock' => 0,
                    'reserved_stock' => 0,
                ]
            );
            $inventoryTo->available_stock += $quantity;
            $inventoryTo->last_movement_at = now();
            $inventoryTo->save();

            // Log de actividad
            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->withProperties([
                    'from_warehouse' => $fromWarehouse->name,
                    'to_warehouse' => $toWarehouse->name,
                    'quantity' => $quantity,
                    'batches' => $transferredBatches,
                ])
                ->log('Traslado de stock realizado');

            return [
                'product_name' => $product->primary_name,
                'from_warehouse' => $fromWarehouse->name,
                'to_warehouse' => $toWarehouse->name,
                'quantity_transferred' => $quantity,
                'batches_processed' => count($transferredBatches),
                'batches_detail' => $transferredBatches,
                'inventory_from_new_stock' => $inventoryFrom->available_stock,
                'inventory_to_new_stock' => $inventoryTo->available_stock,
            ];
        });
    }

    /**
     * Ajuste de entrada de stock (incrementar)
     */
    public function adjustmentIn(
        int $productId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        string $reason,
        ?string $notes = null,
        ?string $expiryDate = null
    ): array {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $unitCost, $reason, $notes, $expiryDate) {

            $product = Product::findOrFail($productId);
            $warehouse = Warehouse::findOrFail($warehouseId);

            // 1. Crear nuevo lote
            $batchCode = 'ADJ-IN-' . now()->format('YmdHis');

            $batch = PurchaseBatch::create([
                'purchase_id' => null, // Sin purchase asociado
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_code' => $batchCode,
                'quantity_purchased' => $quantity,
                'quantity_available' => $quantity,
                'purchase_price' => $unitCost,
                'distribution_price' => $unitCost,
                'purchase_date' => now()->toDateString(),
                'expiry_date' => $expiryDate,
                'status' => 'active',
                'notes' => "Ajuste manual: {$reason}",
            ]);

            // 2. Registrar movimiento
            StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'purchase_batch_id' => $batch->id,
                'type' => 'adjustment',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => 'adjustment_in',
                'reference_id' => null,
                'user_id' => auth()->id(),
                'notes' => $notes ?? "Ajuste de entrada: {$reason}",
                'moved_at' => now(),
            ]);

            // 3. Actualizar inventario
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'available_stock' => 0,
                    'reserved_stock' => 0,
                ]
            );
            $inventory->available_stock += $quantity;
            $inventory->last_movement_at = now();
            $inventory->save();

            // Log
            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->withProperties([
                    'warehouse' => $warehouse->name,
                    'quantity' => $quantity,
                    'reason' => $reason,
                    'batch_code' => $batchCode,
                ])
                ->log('Ajuste de entrada de stock');

            return [
                'product_name' => $product->primary_name,
                'warehouse_name' => $warehouse->name,
                'batch_code' => $batchCode,
                'quantity_added' => $quantity,
                'new_stock' => $inventory->available_stock,
                'unit_cost' => $unitCost,
            ];
        });
    }

    /**
     * Ajuste de salida de stock (decrementar) con FIFO
     */
    public function adjustmentOut(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $reason,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reason, $notes) {

            $product = Product::findOrFail($productId);
            $warehouse = Warehouse::findOrFail($warehouseId);

            // Verificar stock disponible
            $inventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if (!$inventory || $inventory->available_stock < $quantity) {
                throw new \Exception(
                    "Stock insuficiente. Disponible: " . ($inventory->available_stock ?? 0) .
                    ", Requerido: {$quantity}"
                );
            }

            $remainingQty = $quantity;
            $processedBatches = [];

            // Obtener lotes disponibles (FIFO)
            $batches = PurchaseBatch::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('status', 'active')
                ->where('quantity_available', '>', 0)
                ->orderBy('purchase_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($batches as $batch) {
                if ($remainingQty <= 0) break;

                $qtyToDeduct = min($remainingQty, $batch->quantity_available);

                // 1. Descontar del lote
                $batch->quantity_available -= $qtyToDeduct;
                if ($batch->quantity_available == 0) {
                    $batch->status = 'depleted';
                }
                $batch->save();

                // 2. Registrar movimiento
                StockMovement::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'purchase_batch_id' => $batch->id,
                    'type' => 'adjustment',
                    'quantity' => -$qtyToDeduct,
                    'unit_cost' => $batch->distribution_price,
                    'reference_type' => 'adjustment_out',
                    'reference_id' => null,
                    'user_id' => auth()->id(),
                    'notes' => $notes ?? "Ajuste de salida: {$reason}",
                    'moved_at' => now(),
                ]);

                $remainingQty -= $qtyToDeduct;
                $processedBatches[] = [
                    'batch_code' => $batch->batch_code,
                    'quantity_deducted' => $qtyToDeduct,
                ];
            }

            // 3. Actualizar inventario
            $inventory->available_stock -= $quantity;
            $inventory->last_movement_at = now();
            $inventory->save();

            // Log
            activity()
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->withProperties([
                    'warehouse' => $warehouse->name,
                    'quantity' => $quantity,
                    'reason' => $reason,
                    'batches' => $processedBatches,
                ])
                ->log('Ajuste de salida de stock');

            return [
                'product_name' => $product->primary_name,
                'warehouse_name' => $warehouse->name,
                'quantity_removed' => $quantity,
                'new_stock' => $inventory->available_stock,
                'batches_affected' => count($processedBatches),
                'batches_detail' => $processedBatches,
            ];
        });
    }

    /**
     * Obtener lotes disponibles de un producto en un almacén
     */
    public function getAvailableBatches(int $productId, int $warehouseId): Collection
    {
        return PurchaseBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->with(['product', 'warehouse'])
            ->orderBy('purchase_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'quantity_available' => $batch->quantity_available,
                    'purchase_price' => $batch->purchase_price,
                    'distribution_price' => $batch->distribution_price,
                    'purchase_date' => $batch->purchase_date->format('Y-m-d'),
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                    'total_value' => $batch->quantity_available * $batch->distribution_price,
                ];
            });
    }

    /**
     * Obtener historial de movimientos de un producto
     */
    public function getProductMovements(
        int $productId,
        ?int $warehouseId = null,
        ?string $type = null,
        int $perPage = 20
    ) {
        $query = StockMovement::where('product_id', $productId)
            ->with(['warehouse', 'user', 'purchaseBatch']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('moved_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Sincronizar inventario con lotes (útil para correcciones)
     */
    public function syncInventoryWithBatches(?int $productId = null, ?int $warehouseId = null): array
    {
        $query = Inventory::query();

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $synced = 0;
        $errors = [];

        $query->chunk(100, function ($inventories) use (&$synced, &$errors) {
            foreach ($inventories as $inventory) {
                try {
                    $totalAvailable = PurchaseBatch::where('product_id', $inventory->product_id)
                        ->where('warehouse_id', $inventory->warehouse_id)
                        ->where('status', 'active')
                        ->sum('quantity_available');

                    $inventory->update([
                        'available_stock' => $totalAvailable,
                        'last_movement_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'product_id' => $inventory->product_id,
                        'warehouse_id' => $inventory->warehouse_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return [
            'synced_count' => $synced,
            'errors_count' => count($errors),
            'errors' => $errors,
        ];
    }
}

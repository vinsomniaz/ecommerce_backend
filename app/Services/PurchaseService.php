<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Supports\PurchaseDetail;
use App\Models\Supports\PurchaseBatch;
use App\Models\Inventory;
use App\Models\Supports\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PurchaseService
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Crear una nueva compra y actualizar inventario
     */
    public function createPurchase(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear Venta (Cabecera)
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'series' => $data['series'],
                'number' => $data['number'],
                'date' => $data['date'],
                'currency' => $data['currency'],
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'subtotal' => 0, // Se calculará
                'tax' => 0,      // Se calculará
                'total' => 0,    // Se calculará
                'payment_status' => $data['payment_status'] ?? 'pending',
                'user_id' => Auth::id(),
                'notes' => $data['notes'] ?? null,
                'registered_at' => now(),
            ]);

            $subtotal = 0;
            $taxTotal = 0;

            foreach ($data['products'] as $item) {
                // Calcular totales por item
                $quantity = $item['quantity'];
                $price = $item['price']; // Precio unitario de compra
                $itemSubtotal = $quantity * $price;

                // Asumimos que el precio viene SIN IGV por defecto o manejamos lógica de impuesto
                // Para simplificar en este punto, asumimos precio base.
                // Si el sistema maneja impuestos por producto, se debería calcular aquí.
                // Por ahora, sumamos al subtotal global.

                $subtotal += $itemSubtotal;

                // 2. Crear Detalle de Compra
                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'subtotal' => $itemSubtotal,
                    // 'tax_amount' => 0, // Implementar si es necesario
                    // 'total' => $itemSubtotal,
                ]);

                // 3. Generar Lote de Compra (PurchaseBatch)
                $batchCode = "LOT-{$purchase->series}-{$purchase->number}-{$item['product_id']}";

                // Si ya existe un lote con ese código (mismo producto en misma factura), ¿fusionamos o error?
                // Generalmente es un lote único por línea. Si se repite producto, se suma.
                // Aquí asumimos creación directa.

                $batch = PurchaseBatch::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'batch_code' => $batchCode,
                    'quantity_purchased' => $quantity,
                    'quantity_available' => $quantity, // Disponible inicial = comprado
                    'purchase_price' => $price,
                    'purchase_date' => $data['date'],
                    'status' => 'active',
                    'notes' => "Compra #{$purchase->full_document_number}",
                ]);

                // 4. Actualizar Inventario (Inventory)
                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $item['product_id'], 'warehouse_id' => $data['warehouse_id']],
                    ['available_stock' => 0, 'reserved_stock' => 0]
                );

                $inventory->available_stock += $quantity;
                $inventory->last_movement_at = now();
                $inventory->save();

                // 5. Registrar Movimiento de Stock
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'purchase_batch_id' => $batch->id,
                    'type' => 'in', // Entrada por compra
                    'quantity' => $quantity,
                    'unit_cost' => $price,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'user_id' => Auth::id(),
                    'notes' => "Entrada por Compra {$purchase->full_document_number}",
                    'moved_at' => now(),
                ]);

                // 6. Recalcular Costos (Ponderado) y Precios (si aplica)
                // Usamos el servicio de precios existente para mantener coherencia
                $this->pricingService->recalculateInventoryCost($item['product_id'], $data['warehouse_id']);
            }

            // Actualizar totales de la cabecera
            // Asumimos IGV 18% simple por ahora si no viene especificado
            // O si los montos venían netos.
            // Si el backend espera que 'tax' venga del frontend, se debería usar.
            // Aquí calculamos básico:
            $taxRate = 0.18; // Configurable?
            $taxTotal = $subtotal * $taxRate;
            $total = $subtotal + $taxTotal;

            $purchase->update([
                'subtotal' => $subtotal,
                'tax' => $taxTotal,
                'total' => $total,
            ]);

            return $purchase->load('details', 'batches');
        });
    }
}

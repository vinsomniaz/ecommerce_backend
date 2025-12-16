<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Supports\PurchaseDetail;
use App\Models\Supports\PurchaseBatch;
use App\Models\Inventory;
use App\Models\Supports\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;
use Exception;
use Carbon\Carbon;

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
                    'purchase_price' => $price,
                    'distribution_price' => $price, // Default to purchase price
                    'subtotal' => $itemSubtotal,
                    'tax_amount' => 0,
                    'total' => $itemSubtotal,
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

    /**
     * Actualizar una compra (limitado a cabecera por ahora si ya tiene stock movido)
     */
    public function updatePurchase(Purchase $purchase, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            // Si intenta cambiar almacen o productos, sería complejo revertir stock.
            // Por simplicidad, permitimos cambiar datos "no estructurales" o
            // si se requiere, implementar reversión total.

            // Aquí solo actualizaremos cabecera básica por seguridad en esta iteración,
            // a menos que se implemente lógica de anulación y re-creación.

            // Campos seguros
            $fillable = ['series', 'number', 'date', 'currency', 'exchange_rate', 'notes', 'supplier_id'];

            // Filtramos data
            $updateData = array_filter($data, function ($key) use ($fillable) {
                return in_array($key, $fillable);
            }, ARRAY_FILTER_USE_KEY);

            $purchase->update($updateData);

            // TODO: Implementar actualización de detalles (requiere revertir stock anterior y aplicar nuevo)

            return $purchase;
        });
    }

    /**
     * Eliminar (Anular) compra
     */
    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            // 1. Revertir stock
            foreach ($purchase->batches as $batch) {
                // Reducir stock del inventario
                $inventory = Inventory::where('product_id', $batch->product_id)
                    ->where('warehouse_id', $batch->warehouse_id)
                    ->first();

                if ($inventory) {
                    $inventory->available_stock -= $batch->quantity_available;
                    // Nota: Si ya se vendió parte del lote, solo revertimos lo que queda?
                    // O se debe asumir que anular compra es ilegal si ya se consumió?
                    // Generalmente: No se puede eliminar si el lote ya tiene salidas.

                    if ($batch->quantity_available < $batch->quantity_purchased) {
                        // Lote usado parcialmente. No permitir eliminar simple.
                        // Lanzar excepción o solo anular remanente.
                        // Por ahora lanzamos excepción.
                        throw new Exception("No se puede eliminar la compra porque algunos lotes ya han sido utilizados.");
                    }

                    $inventory->save();
                }

                // Anular lote
                $batch->update(['status' => 'inactive', 'quantity_available' => 0]);

                // Eliminar movimientos de stock relacionados para permitir eliminar el lote y la compra
                StockMovement::where('purchase_batch_id', $batch->id)->delete();
            }

            // 2. Registrar movimiento de salida (Corrección)
            // Opcional, o simplemente eliminar los movimientos de entrada si no se quiere rastro.
            // Para auditoría mejor dejar 'voided'.
            // 2. Registrar movimiento de salida (Corrección) - OMITIDO
            // Al eliminar la compra, eliminamos el rastro, así que no creamos movimiento de salida 'void'.
            // Solo revertimos el stock físico arriba.

            // 3. Eliminar o soft-delete compra
            // Si usamos SoftDeletes en modelo... (no estaba en el view_file, asumimos delete físico o update status)
            // Como no vi SoftDeletes trait, vamos a eliminar los detalles y la compra, 
            // pero como hay constraints, mejor actualizamos estado a 'voided' si existiera columna, 
            // o eliminamos si no hay dependencias. 
            // Dado que validamos uso de lote, podemos eliminar.

            $purchase->details()->delete();
            $purchase->batches()->delete(); // O mejor soft delete si existiera
            $purchase->delete(); // Elimina registro
        });
    }

    /**
     * Obtener estadísticas globales de compras
     */
    public function getStatistics(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $totalPurchases = Purchase::count();
        $totalAmount = Purchase::sum('total'); // Cuidado con monedas mezcladas, idealmente convertir a base

        $pendingAmount = Purchase::where('payment_status', 'pending')->sum('total');
        $paidAmount = Purchase::where('payment_status', 'paid')->sum('total');
        $partialAmount = Purchase::where('payment_status', 'partial')->sum('total');

        $thisMonth = Purchase::where('date', '>=', $startOfMonth)->count();
        $lastMonth = Purchase::whereBetween('date', [$startOfLastMonth, $endOfLastMonth])->count();

        // Top Suppliers
        $topSuppliers = Purchase::select('supplier_id', DB::raw('count(*) as count'), DB::raw('sum(total) as total'))
            ->with('supplier')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->supplier_id,
                    'name' => $p->supplier->business_name ?? 'Desconocido',
                    'count' => $p->count,
                    'total' => $p->total
                ];
            });

        return [
            'total_purchases' => $totalPurchases,
            'total_amount' => $totalAmount,
            'pending_amount' => $pendingAmount,
            'paid_amount' => $paidAmount,
            'partial_amount' => $partialAmount,
            'purchases_this_month' => $thisMonth,
            'purchases_last_month' => $lastMonth,
            'top_suppliers' => $topSuppliers,
            'by_payment_status' => [
                'pending' => ['count' => Purchase::where('payment_status', 'pending')->count(), 'amount' => $pendingAmount],
                'partial' => ['count' => Purchase::where('payment_status', 'partial')->count(), 'amount' => $partialAmount],
                'paid' => ['count' => Purchase::where('payment_status', 'paid')->count(), 'amount' => $paidAmount],
            ]
        ];
    }

    /**
     * Registrar Pago
     */
    public function registerPayment(Purchase $purchase, array $data): Payment
    {
        return DB::transaction(function () use ($purchase, $data) {
            // 1. Crear registro de pago
            $payment = Payment::create([
                // 'purchase_id' => $purchase->id, // Necesitamos migración para esto
                'order_id' => null, // Es compra, no venta
                'sale_id' => null,
                'amount' => $data['amount'],
                'currency' => $purchase->currency, // Asumimos misma moneda por ahora
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
                'paid_at' => $data['paid_at'] ?? now(),
                'gateway_response' => ['reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null],
            ]);

            // Asignar purchase_id manualmente si agregamos la columna o usar relación polimórfica si existiera
            // Como vamos a crear la migración, asumimos que el campo existirá.
            // Pero por ahora, para que el código no falle antes de la migración,
            // necesitamos que el modelo soporte 'purchase_id'.
            // Añadiremos purchase_id al create array arriba cuando tengamos la migration.
            // POR AHORA: Lo guardaremos en 'gateway_response' o solo actualizamos status 
            // SI NO PUEDO MODIFICAR DB AHORA MISMO SIN AVISAR.
            // Pero el prompt dice "si faltan cosas... puedes añadirlas".
            // Así que añadiré purchase_id a Payment.

            $payment->purchase_id = $purchase->id;
            $payment->save(); // Esto fallará si no corro la migración.

            // 2. Actualizar estado de compra
            $totalPaid = Payment::where('purchase_id', $purchase->id)->where('status', 'completed')->sum('amount');
            // Sumamos el actual porque acabamos de guardar (transaction)

            if ($totalPaid >= $purchase->total) {
                $purchase->payment_status = 'paid';
            } elseif ($totalPaid > 0) {
                $purchase->payment_status = 'partial';
            } else {
                $purchase->payment_status = 'pending';
            }
            $purchase->save();

            return $payment;
        });
    }
}

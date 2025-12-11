<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\StockManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        protected StockManagementService $stockService
    ) {}

    /**
     * Confirma una orden (pago recibido) y la convierte en Venta.
     * 
     * @param Order $order
     * @param string $paymentMethod Método de pago (ej. 'credit_card', 'izipay')
     * @param string|null $transactionId ID de la transacción de la pasarela
     * @return Sale
     */
    public function confirmOrder(Order $order, string $paymentMethod, ?string $transactionId = null): Sale
    {
        return DB::transaction(function () use ($order, $paymentMethod, $transactionId) {

            // 1. Validar estado
            if ($order->status !== 'pendiente') {
                throw new \Exception("La orden #{$order->id} no está en estado pendiente (Estado actual: {$order->status}).");
            }

            // 2. Crear Venta (Sale)
            // Asumiendo que Order tiene los datos necesarios mapeados
            $sale = Sale::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id, // Debería ser el ID de la Entity (customer)
                'warehouse_id' => $order->warehouse_id,
                // 'sale_type' => 'online', // Removed as it does not exist in migration
                'date' => now(), // Fecha de confirmación
                'currency' => $order->currency,
                'exchange_rate' => 1.0, // TODO: Manejar tasas si es multimoneda
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'total' => $order->total,
                'payment_status' => 'paid', // Se asume pagado al confirmar
                'user_id' => $order->customer_id, // Usuario que compró
                'registered_at' => now(),
            ]);

            // 3. Crear Detalles de Venta y preparar items para stock
            $stockItems = [];

            foreach ($order->details as $detail) {
                // Crear SaleDetail
                // Crear SaleDetail
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $detail->product_id,
                    // 'product_name' => $detail->product_name ?? 'Producto', // Removed
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                    'discount' => $detail->discount,
                    'subtotal' => $detail->subtotal,
                    // 'tax_amount' => $detail->tax_amount, // Removed
                    // 'total' => $detail->total, // Removed
                ]);

                // Agrupar para movimiento de stock
                $stockItems[] = [
                    'product_id' => $detail->product_id,
                    'quantity' => $detail->quantity
                ];
            }

            // 4. Registrar Pago
            $sale->addPayment((float)$order->total, $paymentMethod);
            // Si tuviéramos transaction_id, podríamos guardarlo en notas del pago o campo extra

            // 5. Consumir Stock Reservado (FIFO)
            $this->stockService->confirmSaleFromReserve($order->warehouse_id, $stockItems, $sale->id, $order->stock_allocation);

            // 6. Actualizar Estado de la Orden
            $order->updateStatus('confirmado', 'Pago recibido y venta generada: ' . $sale->id, $transactionId);

            // Opcional: Relacionar la venta con la orden si no está hecho por foreign key inversa
            // $order->sale_id = $sale->id; $order->save();

            Log::info("Orden #{$order->id} convertida a Venta #{$sale->id}");

            return $sale;
        });
    }
}

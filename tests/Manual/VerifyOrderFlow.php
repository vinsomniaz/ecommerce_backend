<?php

namespace Tests\Manual;

use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\Supports\PurchaseBatch; // FIXED Namespace
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// Script to be run inside `php artisan tinker` or via a custom command
// Usage: require_once 'tests/Manual/VerifyOrderFlow.php'; (new \Tests\Manual\VerifyOrderFlow())->run();

class VerifyOrderFlow
{
    public function run()
    {
        echo "🚀 Iniciando verificación de flujo de órdenes...\n";

        DB::beginTransaction();

        try {
            // 1. Setup
            $user = User::factory()->create();
            Auth::login($user);
            $user->entity()->create([
                'type' => 'customer',
                'tipo_documento' => '01',
                'numero_documento' => '12345678',
                'tipo_persona' => 'natural',
                'first_name' => 'Demo',
                'last_name' => 'User',
                'email' => $user->email,
                'is_active' => true
            ]);

            // Reset other main warehouses to avoid conflicts
            Warehouse::query()->update(['is_main' => false]);

            $warehouse = Warehouse::factory()->create(['is_active' => true, 'is_main' => true, 'visible_online' => true]); // FIXED column
            $product = Product::factory()->create(['primary_name' => 'Test Product', 'sku' => 'TEST-001', 'is_active' => true]);

            // Crear lotes (Old y New)
            $oldBatch = PurchaseBatch::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'batch_code' => 'BATCH-OLD',
                'quantity_purchased' => 10,
                'quantity_available' => 10,
                'purchase_price' => 10.00,
                'purchase_date' => now()->subDays(10),
                'status' => 'active'
            ]);

            $newBatch = PurchaseBatch::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'batch_code' => 'BATCH-NEW',
                'quantity_purchased' => 10,
                'quantity_available' => 10, // Total 20
                'purchase_price' => 12.00,
                'purchase_date' => now(),
                'status' => 'active'
            ]);

            Inventory::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'available_stock' => 20,
                'reserved_stock' => 0
            ]);

            echo "✅ Setup completado. Stock inicial: 20\n";

            // 2. Checkout
            $cartService = app(CartService::class);

            // Simular Request ficticio si es necesario, o usar métodos internos si fuera posible
            // Como CartService usa Request, simulamos un carrito
            $cart = $cartService->getCart(new Request());
            $cartService->addOrUpdateItem($cart, $product->id, 15); // Pedimos 15 (Debe tomar 10 Old + 5 New)

            $checkoutData = [
                'customer_data' => [
                    'tipo_documento' => '01',
                    'numero_documento' => '87654321',
                    'email' => 'test@test.com',
                    'first_name' => 'Test',
                    'last_name' => 'Buyer',
                    'phone' => '999999'
                ],
                'address' => ['address' => 'Fake St 123', 'ubigeo' => '150101'],
                'currency' => 'PEN'
            ];

            echo "🛒 Procesando checkout para 15 unidades...\n";
            $order = $cartService->processCheckout($cart, $checkoutData);

            $invAfterOrder = Inventory::where('product_id', $product->id)->first();
            echo "📦 Estado post-checkout (Order #{$order->id}):\n";
            echo "   - Available: {$invAfterOrder->available_stock} (Esperado: 5)\n";
            echo "   - Reserved: {$invAfterOrder->reserved_stock} (Esperado: 15)\n";

            if ($invAfterOrder->reserved_stock !== 15.00 && $invAfterOrder->reserved_stock !== 15) {
                throw new \Exception("❌ Error: Stock reservado incorrecto.");
            }

            // 3. Confirmar Orden -> Venta
            $orderService = app(OrderService::class);
            echo "💰 Confirmando orden (Pago recibido)...\n";
            $sale = $orderService->confirmOrder($order, 'cash');

            $invAfterSale = Inventory::where('product_id', $product->id)->first();
            $oldBatchFresh = $oldBatch->fresh();
            $newBatchFresh = $newBatch->fresh();

            echo "🧾 Venta generada #{$sale->id}.\n";
            echo "📦 Estado post-venta:\n";
            echo "   - Available: {$invAfterSale->available_stock} (Esperado: 5)\n";
            echo "   - Reserved: {$invAfterSale->reserved_stock} (Esperado: 0)\n";

            echo "🏗️ Lotes:\n";
            echo "   - Old Batch: {$oldBatchFresh->quantity_available} (Esperado: 0)\n";
            echo "   - New Batch: {$newBatchFresh->quantity_available} (Esperado: 5)\n";

            if ($oldBatchFresh->quantity_available != 0 || $newBatchFresh->quantity_available != 5) {
                throw new \Exception("❌ Error: FIFO no respetado.");
            }

            DB::rollBack(); // Rollback para no ensuciar DB real
            echo "✅✅✅ PRUEBA EXITOSA (Rollback realizado) ✅✅✅\n";
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMsg = "❌ FALLO DETALLADO:\n";
            $errorMsg .= "Mensaje: " . $e->getMessage() . "\n";
            $errorMsg .= "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
            file_put_contents('verification_error.txt', $errorMsg);
            echo $errorMsg;
        }
    }
}

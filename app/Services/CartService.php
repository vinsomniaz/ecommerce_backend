<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Inventory;
use App\Models\Coupon;
use App\Models\Warehouse;
use App\Models\Order;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Entity;
use App\Http\Requests\Cart\CheckoutRequest;
use App\Models\User;

class CartService
{
    public function __construct(
        protected SettingService $settingService,
        protected StockManagementService $stockService,
        protected AddressService $addressService,
        protected EntityService $entityService
    ) {}

    /**
     * Obtener el carrito actual del usuario/sesión (R1.1)
     */
    public function getCart(Request $request): Cart
    {
        $userId = Auth::id();

        if (!$userId) {
            throw ApiException::unauthorized('La autenticación es requerida para gestionar el carrito.');
        }

        $cart = Cart::firstOrCreate(['user_id' => $userId], ['session_id' => '']);
        // Carrito de invitado
        return $cart;
    }

    /**
     * Agregar o actualizar producto en el carrito con validación de stock (R1.2)
     */
    public function addOrUpdateItem(Cart $cart, int $productId, int $quantity): CartDetail
    {
        return DB::transaction(function () use ($cart, $productId, $quantity) {
            // 1. Determinar almacén principal de venta online
            $mainWarehouse = Warehouse::main()->visibleOnline()->first();
            if (!$mainWarehouse) {
                throw ApiException::badRequest('No hay un almacén principal de venta online configurado.', 'NO_MAIN_WAREHOUSE');
            }

            $inventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $mainWarehouse->id)
                ->first();

            $availableStock = $inventory?->available_stock ?? 0;

            if ($quantity <= 0) {
                $cart->removeProduct($productId);
                throw ApiException::badRequest('Cantidad no válida, producto eliminado del carrito.', 'INVALID_QUANTITY');
            }

            // 2. Validar Stock (R1.2)
            if ($quantity > $availableStock) {
                throw new InsufficientStockException(
                    "Stock insuficiente para el producto. Disponible: {$availableStock}",
                    $quantity,
                    $availableStock
                );
            }

            // 3. Agregar/Actualizar
            $detail = $cart->details()->where('product_id', $productId)->first();

            if ($detail) {
                $detail->update(['quantity' => $quantity]);
            } else {
                $detail = $cart->details()->create(['product_id' => $productId, 'quantity' => $quantity]);
            }

            // 4. Recalcular
            $this->recalculateTotals($cart);

            return $detail->fresh(['product']);
        });
    }

    /**
     * Eliminar producto del carrito
     */
    public function removeItem(Cart $cart, int $productId): void
    {
        $cart->removeProduct($productId);
        $this->recalculateTotals($cart);
    }



    /**
     * Calcular y obtener totales (R1.3, R2.2)
     */
    public function recalculateTotals(Cart $cart): array
    {
        $cart->load('details.product.firstWarehouseInventory');

        $subtotal = 0.0;
        $totalCost = 0.0;
        $igvRate = $this->settingService->get('sales', 'igv_rate', 0.18); // 18%

        foreach ($cart->details as $detail) {
            $unitPrice = $detail->unit_price;
            $quantity = $detail->quantity;

            $itemSubtotal = $unitPrice * $quantity;
            $subtotal += $itemSubtotal;
            $totalCost += ($detail->product->average_cost * $quantity);
        }

        // 1. Base Imponible es el Subtotal
        $taxableBase = $subtotal;

        // 2. Calcular IGV/Impuestos (R2.2)
        $tax = $taxableBase * $igvRate;

        // 3. Costo de Envío (R3.2)
        $shippingCost = $this->settingService->get('ecommerce', 'default_shipping_cost', 0.0);

        // 4. Total a Pagar (R2.2)
        $total = $taxableBase + $tax + $shippingCost;

        $results = [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'shipping_cost' => round($shippingCost, 2),
            'total' => round($total, 2),
            'total_cost' => round($totalCost, 2),
        ];

        return $results;
    }

    /**
     * Procesar el checkout y crear la orden (R3)
     */
    public function processCheckout(Cart $cart, array $checkoutData): Order
    {
        return DB::transaction(function () use ($cart, $checkoutData) {
            if ($cart->isEmpty()) {
                throw ApiException::badRequest('El carrito está vacío.', 'CART_IS_EMPTY');
            }
            if (!Auth::check()) {
                throw ApiException::unauthorized('Usuario no autenticado.');
            }

            $cart->load('details.product');

            // 1. Re-validar stock (R1.2)
            $this->validateAllStock($cart);

            // 2. Integración con Entidades (R3.1)
            $user = Auth::user();
            $customerData = $checkoutData['customer_data'];
            $customerEntity = $this->updateOrCreateCustomerEntity($user, $customerData);

            // 3. Gestión de Direcciones de Envío (R3.2)
            $shippingAddress = $this->addressService->createForEntity(
                $customerEntity,
                $checkoutData['address']
            );
            $warehouse = Warehouse::main()->visibleOnline()->firstOrFail();

            // 4. Bloqueo de Stock (Soft Reserve) (R3.3)
            $this->softReserveStock($cart, $warehouse->id);

            // 5. Re-validar totales (R1.3)
            $totals = $this->recalculateTotals($cart);
            $igvRate = $this->settingService->get('sales', 'igv_rate', 0.18); // Obtener tasa de IGV para item level
            
            // 6. Creación de la Orden (Estado Pendiente) (R3.3)
            $order = Order::create([
                'customer_id' => $user->id,
                'warehouse_id' => $warehouse->id,
                'shipping_address_id' => $shippingAddress->id,
                'coupon_id' => $cart->coupon_id,
                'status' => 'pendiente', // Usar 'pendiente' según app/Models/Order.php
                'currency' => $checkoutData['currency'] ?? 'PEN',
                'subtotal' => $totals['subtotal'],
                'discount' => 0.00,
                'coupon_discount' => 0.00,
                'tax' => $totals['tax'],
                'shipping_cost' => $totals['shipping_cost'],
                'total' => $totals['total'],
                'order_date' => now(),
                'observations' => $checkoutData['observations'] ?? null,
            ]);

            // 7. Mover items del carrito a OrderDetails (asumo modelo OrderDetail)
            foreach ($cart->details as $detail) {
                // Re-obtener el precio unitario final para el detalle
                $unitPrice = $detail->unit_price;
                $quantity = $detail->quantity;
                
                // Cálculo de subtotales/totales del item
                $subtotal = $unitPrice * $quantity;
                $taxAmount = $subtotal * $igvRate;
                $total = $subtotal + $taxAmount;

                $order->details()->create([
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product_name, // Usando accessor de CartDetail
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0.00, // No hay descuento en CartDetail
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);
            }

            // 8. Limpiar el carrito y registrar historial de estado
            $cart->update(['converted_to_order_at' => now()]);
            $cart->clear();

            $order->updateStatus('pendiente', 'Orden creada y stock reservado', null);

            return $order->fresh(['shippingAddress', 'warehouse', 'statusHistory']);
        });
    }

    /**
     * Lógica de reserva de stock (R3.3 - Bloqueo de Stock)
     */
    protected function softReserveStock(Cart $cart, int $warehouseId): void
    {
        $reserves = $cart->details->map(fn($d) => [
            'product_id' => $d->product_id,
            'quantity' => $d->quantity
        ])->toArray();

        $this->stockService->softReserve($warehouseId, $reserves);
    }

    /**
     * Actualiza o crea la Entity del cliente autenticado (R3.1)
     */
    protected function updateOrCreateCustomerEntity(User $user, array $customerData): Entity
    {
        $entity = $user->entity;

        // Forzar tipo de persona basado en documento
        $tipoPersona = $customerData['tipo_documento'] === '01' ? 'natural' : 'juridica';

        // Si ya existe, actualiza
        if ($entity) {
            $updateData = [
                'tipo_documento' => $customerData['tipo_documento'],
                'numero_documento' => $customerData['numero_documento'],
                'tipo_persona' => $tipoPersona,
                'email' => $customerData['email'],
                'phone' => $customerData['phone'] ?? null,
            ];
            if ($tipoPersona === 'natural') {
                $updateData['first_name'] = $customerData['first_name'];
                $updateData['last_name'] = $customerData['last_name'];
                $updateData['business_name'] = null;
            } else {
                $updateData['business_name'] = $customerData['business_name'];
                $updateData['first_name'] = null;
                $updateData['last_name'] = null;
            }

            $entity = $this->entityService->update($entity, $updateData);
        } else {
            // Si no existe, crear
            $createData = [
                'user_id' => $user->id,
                'type' => 'customer',
                'tipo_documento' => $customerData['tipo_documento'],
                'numero_documento' => $customerData['numero_documento'],
                'tipo_persona' => $tipoPersona,
                'email' => $customerData['email'],
                'phone' => $customerData['phone'] ?? null,
                'is_active' => true,
            ];
            if ($tipoPersona === 'natural') {
                $createData['first_name'] = $customerData['first_name'];
                $createData['last_name'] = $customerData['last_name'];
            } else {
                $createData['business_name'] = $customerData['business_name'];
            }

            $entity = $this->entityService->create($createData);
        }

        // Actualizar datos de User si cambiaron
        $user->update([
            'email' => $customerData['email'],
            'first_name' => $customerData['first_name'] ?? $user->first_name,
            'last_name' => $customerData['last_name'] ?? $user->last_name,
            'cellphone' => $customerData['phone'] ?? $user->cellphone,
        ]);

        return $entity;
    }

    /**
     * Re-valida el stock de todos los items en el carrito (Final Check)
     */
    protected function validateAllStock(Cart $cart): void
    {
        $mainWarehouse = Warehouse::main()->visibleOnline()->first();
        if (!$mainWarehouse) {
            throw ApiException::badRequest('No hay un almacén principal de venta online configurado.', 'NO_MAIN_WAREHOUSE');
        }

        $itemsToValidate = $cart->details->pluck('quantity', 'product_id')->toArray();
        $warehouseId = $mainWarehouse->id;

        foreach ($itemsToValidate as $productId => $quantity) {
            $inventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $availableStock = $inventory?->available_stock ?? 0;
            $productName = $inventory->product->primary_name ?? 'Producto ID ' . $productId;

            if ($quantity > $availableStock) {
                throw new InsufficientStockException(
                    "Stock insuficiente para {$productName} en el checkout. Disponible: {$availableStock}",
                    $quantity,
                    $availableStock
                );
            }
        }
    }
}

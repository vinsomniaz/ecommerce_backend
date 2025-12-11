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
     * Agregar o actualizar producto en el carrito con validación de stock GLOBAL (R1.2)
     */
    public function addOrUpdateItem(Cart $cart, int $productId, int $quantity): CartDetail
    {
        return DB::transaction(function () use ($cart, $productId, $quantity) {

            // 1. Usar Stock Global (suma de almacenes)
            $product = \App\Models\Product::findOrFail($productId);
            $availableStock = $product->total_stock; // Accessor que suma inventories

            if ($quantity <= 0) {
                $cart->removeProduct($productId);
                throw ApiException::badRequest('Cantidad no válida, producto eliminado del carrito.', 'INVALID_QUANTITY');
            }

            // 2. Validar Stock Global (R1.2)
            if ($quantity > $availableStock) {
                throw new InsufficientStockException(
                    "Stock insuficiente para el producto. Disponible Total: {$availableStock}",
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
    public function recalculateTotals(Cart $cart, string $targetCurrency = 'PEN', float $exchangeRate = 1.0): array
    {
        $cart->load('details.product.firstWarehouseInventory');

        $subtotal = 0.0;
        $totalCost = 0.0;
        $igvRate = $this->settingService->get('sales', 'igv_rate', 0.18); // 18%

        foreach ($cart->details as $detail) {
            // El unit_price del modelo CartDetail obtiene el precio del producto en PEN (base)
            $unitPriceInPen = $detail->unit_price;

            // Convertimos si es necesario para el cálculo interno, 
            // aunque generalmente calculamos todo en base y convertimos al final.
            // PERO, para consistencia con el frontend que sumará items convertidos:
            $quantity = $detail->quantity;

            $itemSubtotal = $unitPriceInPen * $quantity;
            $subtotal += $itemSubtotal;
            $totalCost += ($detail->product->average_cost * $quantity);
        }

        // 1. Base Imponible es el Subtotal (en PEN)
        $taxableBase = $subtotal;

        // 2. Calcular IGV/Impuestos (R2.2) (en PEN)
        $tax = $taxableBase * $igvRate;

        // 3. Costo de Envío (R3.2) (en PEN)
        $shippingCost = $this->settingService->get('ecommerce', 'default_shipping_cost', 0.0);

        // 4. Total a Pagar (R2.2) (en PEN)
        $total = $taxableBase + $tax + $shippingCost;

        // 5. Conversión de Moneda
        // Si la tasa es > 1, dividimos (PEN / Tasa = USD)
        // Ej: 100 PEN / 3.75 = 26.66 USD
        if ($exchangeRate > 1.0 && $targetCurrency !== 'PEN') {
            $subtotal = $taxableBase / $exchangeRate;
            $tax = ($taxableBase * $igvRate) / $exchangeRate;
            $shippingCost = $shippingCost / $exchangeRate;
            $totalCost = $totalCost / $exchangeRate;
            $total = $total / $exchangeRate;
        }

        $results = [
            'currency' => $targetCurrency,
            'exchange_rate' => $exchangeRate,
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

            // 1. Re-validar stock GLOBAL (R1.2)
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
            $mainWarehouse = Warehouse::main()->visibleOnline()->firstOrFail();

            // 4. Cálculo y Bloqueo de Stock GLOBAL (R3.3)
            $itemsToReserve = $cart->details->map(fn($d) => [
                'product_id' => $d->product_id,
                'quantity' => $d->quantity
            ])->toArray();

            // Calcular Allocation
            $allocation = $this->stockService->calculateGlobalAllocation($itemsToReserve);

            // Reservar según Allocation
            $this->stockService->softReserveAllocated($allocation);

            // Generar nota de Allocation (si hay split)
            $allocationNotes = [];
            foreach ($allocation as $allocItem) {
                if (count($allocItem['allocation']) > 1) {
                    $details = implode(', ', array_map(fn($a) => "{$a['warehouse_name']}: {$a['quantity']}", $allocItem['allocation']));
                    $allocationNotes[] = "Prod #{$allocItem['product_id']} -> [{$details}]";
                } elseif (isset($allocItem['allocation'][0]) && $allocItem['allocation'][0]['warehouse_id'] != $mainWarehouse->id) {
                    // Si se toma de un almacén secundario, también avisar
                    $wName = $allocItem['allocation'][0]['warehouse_name'];
                    $allocationNotes[] = "Prod #{$allocItem['product_id']} -> Tomado de {$wName}";
                }
            }
            $obs = $checkoutData['observations'] ?? '';
            if (!empty($allocationNotes)) {
                $obs .= "\n[Stock Multi-Almacén]: " . implode('; ', $allocationNotes);
            }

            // 5. Re-validar totales (R1.3)
            $totals = $this->recalculateTotals($cart);
            $igvRate = $this->settingService->get('sales', 'igv_rate', 0.18);

            // 6. Conversión de Moneda
            $targetCurrency = strtoupper($checkoutData['currency'] ?? 'PEN');
            $exchangeRate = 1.0;

            if ($targetCurrency !== 'PEN') {
                $exchangeRate = \App\Models\ExchangeRate::getRate($targetCurrency);
                if (!$exchangeRate) {
                    throw ApiException::badRequest("La moneda {$targetCurrency} no está soportada o no tiene tipo de cambio registrado.");
                }

                // Convertir montos (Base PEN / Rate = Target)
                // Ej: 375 PEN / 3.75 = 100 USD
                $totals['subtotal'] = round($totals['subtotal'] / $exchangeRate, 2);
                $totals['tax'] = round($totals['tax'] / $exchangeRate, 2);
                $totals['shipping_cost'] = round($totals['shipping_cost'] / $exchangeRate, 2);
                $totals['discount'] = round(($totals['discount'] ?? 0) / $exchangeRate, 2);
                $totals['coupon_discount'] = round(($totals['coupon_discount'] ?? 0) / $exchangeRate, 2);
                $totals['total'] = round($totals['total'] / $exchangeRate, 2);
            }

            // 7. Crear Orden de Venta
            $order = Order::create([
                'customer_id' => $customerEntity->id,
                'user_id' => $user->id,
                'warehouse_id' => $mainWarehouse->id, // El pedido nace asociado al principal (luego el Allocation dice la verdad)
                'shipping_address_id' => $shippingAddress->id,
                'coupon_id' => $cart->coupon_id,
                'sale_type' => 'store',
                'status' => 'pendiente',

                'currency' => $targetCurrency,
                // 'exchange_rate' => $exchangeRate, // Si existiera en tabla orders

                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'] ?? 0.00, // Ensure discount is set, default to 0 if not in totals
                'coupon_discount' => $totals['coupon_discount'] ?? 0.00, // No convertido en recalculateTotals? Asumimos que sí o es 0
                'tax' => $totals['tax'],
                'shipping_cost' => $totals['shipping_cost'],
                'total' => $totals['total'],

                'stock_allocation' => $allocation, // JSON field
                'observations' => trim($obs),
                'order_date' => now(),
            ]);

            // 8. Mover items del carrito a OrderDetails
            foreach ($cart->details as $detail) {
                // Convertir unit price
                $unitPrice = $detail->unit_price;
                if ($exchangeRate > 1.0) {
                    $unitPrice = round($unitPrice / $exchangeRate, 2);
                }

                $quantity = $detail->quantity;
                $subtotal = $unitPrice * $quantity;
                // Recalcular impuestos sobre el nuevo subtotal convertido
                // Nota: Esto asume precio sin IGV o con IGV según lógica de negocio.
                // Si unit_price ya incluye IGV, el taxAmount se desglosa. Si no, se agrega.
                // La lógica original sumaba taxAmount = subtotal * igvRate. Mantenemos eso.
                $taxAmount = round($subtotal * $igvRate, 2);
                $total = $subtotal + $taxAmount;

                $order->details()->create([
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product_name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0.00,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);
            }

            // 8. Limpiar el carrito y registrar historial de estado
            $cart->update(['converted_to_order_at' => now()]);
            $cart->clear();

            $order->updateStatus('pendiente', 'Orden creada con reserva global', null);

            return $order->fresh(['shippingAddress', 'warehouse', 'statusHistory']);
        });
    }

    /**
     * Lógica de reserva de stock (DEPRECATED - Usado legacy)
     */
    protected function softReserveStock(Cart $cart, int $warehouseId): void
    {
        // Redirigir a lógica global asumiendo un solo almacén si se usa aun
        // Por seguridad, mantenemos la implementación antigua o la eliminamos.
        // Al haber cambiado processCheckout, este método ya no se llama desde ahí.
        // Lo dejamos vacío o lanzamos error para evitar uso accidental.
    }

    // ...

    /**
     * Re-valida el stock Global de todos los items en el carrito (Final Check)
     */
    protected function validateAllStock(Cart $cart): void
    {
        $itemsToValidate = $cart->details->pluck('quantity', 'product_id')->toArray();

        foreach ($itemsToValidate as $productId => $quantity) {
            $product = \App\Models\Product::findOrFail($productId);
            $availableStock = $product->total_stock; // Global Stock

            if ($quantity > $availableStock) {
                throw new InsufficientStockException(
                    "Stock insuficiente para {$product->primary_name} en el checkout. Disponible Global: {$availableStock}",
                    $quantity,
                    $availableStock
                );
            }
        }
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
}

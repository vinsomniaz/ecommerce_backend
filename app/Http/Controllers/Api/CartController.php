<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Http\Requests\Cart\AddItemRequest;
use App\Http\Requests\Cart\UpdateItemRequest;
use App\Http\Requests\Cart\CheckoutRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

use App\Models\ExchangeRate;
use App\Http\Resources\Products\ProductResource;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * Obtener el carrito actual y sus totales (R1.1, R2.2)
     * GET /api/ecommerce/cart
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request);

        // Detectar moneda
        [$currency, $exchangeRate] = $this->getCurrencyParams($request);

        // Inyectar factor para ProductResource
        $request->merge(['exchange_rate_factor' => $exchangeRate]);

        // Calcular totales convertidos
        $totals = $this->cartService->recalculateTotals($cart, $currency, $exchangeRate);

        // Transformar items para convertir precios unitarios
        $cart->load('details.product');
        $items = $cart->details->map(function ($detail) use ($exchangeRate) {
            // Calcular precio unitario y subtotal convertidos
            // Usamos el método del producto que acepta el factor
            $unitPrice = $detail->product->getSalePrice(null, null, $exchangeRate) ?? 0;
            $subtotal = $unitPrice * $detail->quantity;

            return [
                'id' => $detail->id,
                'cart_id' => $detail->cart_id,
                'product_id' => $detail->product_id,
                'quantity' => $detail->quantity,
                'unit_price' => round($unitPrice, 2),
                'subtotal' => round($subtotal, 2),
                'product_name' => $detail->product_name,
                'product_image' => $detail->product_image,
                'product' => new ProductResource($detail->product),
                'created_at' => $detail->created_at,
                'updated_at' => $detail->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'items' => $items,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * Agregar producto (R1.2)
     * POST /api/ecommerce/cart/items
     */
    public function addItem(AddItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request);

        $detail = $this->cartService->addOrUpdateItem(
            $cart,
            $request->product_id,
            $request->quantity
        );

        [$currency, $exchangeRate] = $this->getCurrencyParams($request);
        $request->merge(['exchange_rate_factor' => $exchangeRate]);

        // Convertir el detalle retornado (si es necesario para la UI inmediata)
        // Por simplicidad, retornamos el detalle base, pero los TOTALES sí convertidos

        return response()->json([
            'success' => true,
            'message' => 'Producto agregado al carrito',
            'data' => $detail,
            'totals' => $this->cartService->recalculateTotals($cart->fresh(), $currency, $exchangeRate),
        ], 201);
    }

    /**
     * Actualizar cantidad de ítem (R1.2)
     * PATCH /api/ecommerce/cart/items/{productId}
     */
    public function updateItem(UpdateItemRequest $request, int $productId): JsonResponse
    {
        $cart = $this->cartService->getCart($request);

        $detail = $this->cartService->addOrUpdateItem(
            $cart,
            $productId,
            $request->quantity
        );

        [$currency, $exchangeRate] = $this->getCurrencyParams($request);

        return response()->json([
            'success' => true,
            'message' => 'Cantidad actualizada',
            'data' => $detail,
            'totals' => $this->cartService->recalculateTotals($cart->fresh(), $currency, $exchangeRate),
        ]);
    }

    /**
     * Eliminar item
     * DELETE /api/ecommerce/cart/items/{productId}
     */
    public function removeItem(Request $request, int $productId): JsonResponse
    {
        $cart = $this->cartService->getCart($request);
        $this->cartService->removeItem($cart, $productId);

        [$currency, $exchangeRate] = $this->getCurrencyParams($request);

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado del carrito',
            'totals' => $this->cartService->recalculateTotals($cart->fresh(), $currency, $exchangeRate),
        ]);
    }

    /**
     * Procesar Checkout (R3)
     * POST /api/ecommerce/checkout
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        // Se requiere autenticación y rol 'customer' (validado en CheckoutRequest)
        $cart = $this->cartService->getCart($request);
        $order = $this->cartService->processCheckout($cart, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Orden de compra creada exitosamente. Esperando pago.',
            'data' => $order,
        ], 201);
    }

    /**
     * Helper para obtener params de moneda
     */
    private function getCurrencyParams(Request $request): array
    {
        $currency = strtoupper($request->query('currency', 'PEN'));
        $exchangeRate = 1.0;

        if ($currency !== 'PEN') {
            $exchangeRate = ExchangeRate::getRate($currency) ?? 1.0;
        }

        return [$currency, $exchangeRate];
    }
}

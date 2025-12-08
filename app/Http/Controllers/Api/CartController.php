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
        $totals = $this->cartService->recalculateTotals($cart);

        return response()->json([
            'success' => true,
            'data' => [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'items' => $cart->details->load('product'),
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

        return response()->json([
            'success' => true,
            'message' => 'Producto agregado al carrito',
            'data' => $detail,
            'totals' => $this->cartService->recalculateTotals($cart->fresh()),
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

        return response()->json([
            'success' => true,
            'message' => 'Cantidad actualizada',
            'data' => $detail,
            'totals' => $this->cartService->recalculateTotals($cart->fresh()),
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

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado del carrito',
            'totals' => $this->cartService->recalculateTotals($cart->fresh()),
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
}
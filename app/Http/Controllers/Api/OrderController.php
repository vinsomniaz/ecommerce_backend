<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'details', 'statusHistory']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('from_date')) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        // Default sort
        $query->orderBy('created_at', 'desc');

        $orders = $query->paginate($request->get('per_page', 15));

        return response()->json($orders);
    }

    /**
     * Store a newly created order in storage.
     * (Generally created via Ecommerce, but here for completeness or manual admin creation)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:entities,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Logic to create order would go here. 
        // For now, focusing on connection.
        // Assuming simple creation for now if needed.

        // Use a service or logic similar to Cart checkout. 
        // Leaving placeholder as this might be complex (stock, prices).
        // If user wants to create orders from ERP manually, we might need OrderService create method.

        return response()->json(['message' => 'Manual order creation not fully implemented yet'], 501);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['customer', 'details', 'statusHistory', 'shippingAddress']);
        return response()->json($order);
    }

    /**
     * Update the specified order in storage.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        // Mostly for status updates or admin overrides
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pendiente,confirmado,preparando,enviado,entregado,cancelado',
            'notes' => 'nullable|string',
            'tracking_code' => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            $order->updateStatus(
                $validated['status'],
                $validated['notes'] ?? null,
                $validated['tracking_code'] ?? null
            );
        }

        // Can add logic to update other fields if needed

        return response()->json($order->fresh(['statusHistory']));
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        // Check if can be cancelled/deleted
        if ($order->can_be_cancelled) {
            $order->cancel("Cancelado por administrador desde ERP");
            return response()->json(['message' => 'Order cancelled successfully']);
        }

        return response()->json(['message' => 'Order cannot be cancelled in its current status'], 400);
    }
}

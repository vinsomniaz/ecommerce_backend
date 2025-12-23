<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            'currency' => 'required|string|size:3',
            'status' => 'required|string',
            'payment_status' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            DB::beginTransaction();

            // 1. Calculate Totals (Back-end validation recommended)
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $quantity = $item['quantity'];
                $price = $item['unit_price'];
                $discountPercent = $item['discount'] ?? 0;

                $lineTotal = $quantity * $price;
                $discountAmount = ($lineTotal * $discountPercent) / 100;
                $lineSubtotal = $lineTotal - $discountAmount;

                $subtotal += $lineSubtotal;

                // Prepare detail data
                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => \App\Models\Product::find($item['product_id'])->primary_name ?? 'Producto', // Fallback or Eager load
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'discount' => $discountPercent,
                    'subtotal' => $lineSubtotal,
                    // Tax per line if needed, or global. Assuming global logic for simplicity
                    'tax_amount' => 0, // Placeholder
                    'total' => $lineSubtotal // Placeholder
                ];
            }

            // Global Tax (e.g. IGV 18% in Peru if not included)
            // Assuming prices exclude tax for calculation base, or following frontend logic
            // Frontend: subtotal / 1.18 = base.
            // Let's trust frontend totals or recalculate simple logic:
            // If we follow frontend logic:
            // Total = Subtotal (calculated above which is effectively Total to Pay)
            // Base = Total / 1.18
            // Tax = Total - Base

            $total = $subtotal;
            $tax = $total - ($total / 1.18);
            $base = $total / 1.18;

            // 2. Create Order
            $order = Order::create([
                'customer_id' => $validated['customer_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'order_date' => Carbon::parse($validated['order_date']),
                'currency' => $validated['currency'],
                'status' => $validated['status'],
                'shipping_address_id' => Entity::find($validated['customer_id'])->addresses()->first()?->id ?? \App\Models\Address::first()?->id ?? 1, // Fallback to avoid 500 if no address exists
                'subtotal' => $base,
                'tax' => $tax,
                'total' => $total,
                'discount' => 0, // Ignoring line discounts aggregation for now or should calculate? 
                'shipping_cost' => 0,
                'observations' => $validated['notes'] ?? null,
            ]);

            // 3. Create Details
            foreach ($itemsData as $data) {
                $order->details()->create($data);
            }

            // 4. Update Stock?
            // If status is completed or depending on logic. keeping simple for now.

            DB::commit();

            return response()->json($order->load(['customer', 'details']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear el pedido: ' . $e->getMessage()], 500);
        }
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

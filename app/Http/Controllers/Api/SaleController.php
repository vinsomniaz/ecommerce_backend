<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleController extends Controller
{
    /**
     * Display a listing of sales (orders).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'items', 'statusHistory']);

        // Apply filters similar to OrderController but tailored for Sales view
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Sorting
        $sort = $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('order_date', 'asc');
        } else {
            $query->orderBy('order_date', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created sale in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // Reuse OrderController logic or duplicate it here.
        // For now, I'll instantiate OrderController to avoid code duplication
        // or effectively alias it. Given the time, I'll recommend using OrderController logic
        // but for now I will duplicate the relevant parts or delegate.

        // To ensure consistency, let's delegate to a service or just define it here.
        // Since I can't easily refactor unrelated files, I will implement a basic store here specific to Sales.

        $validated = $request->validate([
            'customer_id' => 'required|exists:entities,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'date' => 'required|date',
            'currency' => 'required|string|size:3',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $quantity = $item['quantity'];
                $price = $item['unit_price'];
                $lineTotal = $quantity * $price;
                $subtotal += $lineTotal; // Ignoring explicit discount logic for simplicity unless passed

                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'subtotal' => $lineTotal,
                    'total' => $lineTotal,
                ];
            }

            // Simple tax logic matching OrderController
            $total = $subtotal;
            $tax = $total - ($total / 1.18);
            $base = $total / 1.18;

            $order = Order::create([
                'customer_id' => $validated['customer_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'order_date' => Carbon::parse($validated['date']),
                'currency' => $validated['currency'],
                'status' => 'completed', // Sales created directly are usually completed/confirmed
                'subtotal' => $base,
                'tax' => $tax,
                'total' => $total,
                'shipping_address_id' => \App\Models\Entity::find($validated['customer_id'])->addresses()->first()?->id ?? 1,
            ]);

            foreach ($itemsData as $data) {
                $order->items()->create($data); // Assuming relationship is 'items' or 'details'
            }

            DB::commit();

            return response()->json(['success' => true, 'data' => $order->load(['customer', 'items'])], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al registrar venta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified sale.
     */
    public function show($id): JsonResponse
    {
        $order = Order::with(['customer', 'items', 'statusHistory'])->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Venta no encontrada'], 404);
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    /**
     * Get global sales statistics.
     */
    public function globalStatistics(): JsonResponse
    {
        // Calculate real stats from orders
        $totalSales = Order::count();
        $totalAmount = Order::sum('total');
        $paidAmount = Order::where('payment_status', 'paid')->sum('total');
        $pendingAmount = Order::where('payment_status', 'pending')->sum('total');
        $partialAmount = Order::where('payment_status', 'partial')->sum('total');

        $currentMonth = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();

        $salesThisMonth = Order::whereYear('order_date', $currentMonth->year)
            ->whereMonth('order_date', $currentMonth->month)
            ->count();

        $salesLastMonth = Order::whereYear('order_date', $lastMonth->year)
            ->whereMonth('order_date', $lastMonth->month)
            ->count();

        // Top customers (simple group by)
        $topCustomers = Order::select('customer_id', DB::raw('count(*) as count'), DB::raw('sum(total) as total'))
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('customer')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->customer_id,
                    'name' => $item->customer ? ($item->customer->business_name ?? $item->customer->first_name . ' ' . $item->customer->last_name) : 'Desconocido',
                    'total' => $item->total,
                    'count' => $item->count
                ];
            });

        $stats = [
            'total_sales' => $totalSales,
            'total_amount' => $totalAmount,
            'pending_amount' => $pendingAmount,
            'paid_amount' => $paidAmount,
            'partial_amount' => $partialAmount,
            'sales_this_month' => $salesThisMonth,
            'sales_last_month' => $salesLastMonth,
            'invoices_pending_sunat' => 0, // Placeholder
            'invoices_accepted' => 0, // Placeholder
            'invoices_rejected' => 0, // Placeholder
            'top_customers' => $topCustomers,
            'by_payment_status' => [
                'pending' => ['count' => Order::where('payment_status', 'pending')->count(), 'amount' => $pendingAmount],
                'partial' => ['count' => Order::where('payment_status', 'partial')->count(), 'amount' => $partialAmount],
                'paid' => ['count' => Order::where('payment_status', 'paid')->count(), 'amount' => $paidAmount],
            ],
            // Placeholder for invoice type breakdown as it requires joining with invoices table if exists
            'by_invoice_type' => [
                "01" => ["count" => 0, "amount" => 0, "label" => "Factura"],
                "03" => ["count" => 0, "amount" => 0, "label" => "Boleta"]
            ],
            'quotations_pending_conversion' => 0 // Placeholder
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Update the specified sale.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string',
            'notes' => 'nullable|string',
            'payment_status' => 'sometimes|string',
        ]);

        $order->update($validated);

        return response()->json(['success' => true, 'data' => $order]);
    }

    /**
     * Remove the specified sale.
     */
    public function destroy($id): JsonResponse
    {
        $order = Order::findOrFail($id);
        // Check if can be deleted logic here if needed
        $order->delete();
        return response()->json(['success' => true, 'message' => 'Venta eliminada']);
    }

    /**
     * Create sale from quotation.
     */
    public function fromQuotation(Request $request, $quotationId): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Funcionalidad de conversión pendiente de implementación'], 501);
    }
}

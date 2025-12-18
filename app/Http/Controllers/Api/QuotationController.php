<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\LowMarginException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\GetQuotationProductsRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Requests\Quotation\UpdateQuotationRequest;
use App\Http\Resources\Quotation\QuotationProductCollection;
use App\Services\MarginCalculatorService;
use App\Services\SettingService;
use App\Http\Requests\Quotation\AddItemRequest;
use App\Http\Requests\Quotation\UpdateItemRequest;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\SupplierProductResource;
use App\Models\Inventory;
use App\Models\Quotation;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationService $quotationService
    ) {}

    /**
     * Display a listing of quotations
     */
    public function index(Request $request): JsonResponse
    {
        $query = Quotation::with(['customer', 'user', 'warehouse'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('quotation_code', 'like', "%{$request->search}%")
                        ->orWhere('customer_name', 'like', "%{$request->search}%")
                        ->orWhere('customer_document', 'like', "%{$request->search}%");
                });
            })
            ->latest('quotation_date');

        $quotations = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => QuotationResource::collection($quotations->items()),
            'meta' => [
                'current_page' => $quotations->currentPage(),
                'last_page' => $quotations->lastPage(),
                'per_page' => $quotations->perPage(),
                'total' => $quotations->total(),
            ],
        ]);
    }

    /**
     * Store a newly created quotation
     */
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        try {
            $quotation = $this->quotationService->create(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'message' => 'Cotización creada exitosamente',
                'data' => new QuotationResource($quotation),
            ], 201);
        } catch (LowMarginException $e) {
            return response()->json([
                'error' => 'low_margin',
                'message' => $e->getMessage(),
            ], 422);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'error' => 'insufficient_stock',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'server_error',
                'message' => 'Error al crear la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified quotation
     */
    public function show(Quotation $quotation): JsonResponse
    {
        $quotation->load([
            'details.product',
            'details.warehouse',
            'details.supplier',
            'details.supplierProduct',
            'customer',
            'user',
            'warehouse',
        ]);

        return response()->json([
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Update the specified quotation
     */
    public function update(UpdateQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        // Solo se pueden editar cotizaciones en draft
        if ($quotation->status !== 'draft') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden editar cotizaciones en estado borrador',
            ], 422);
        }

        try {
            $quotation->update($request->validated());
            $this->quotationService->recalculateTotals($quotation);

            return response()->json([
                'message' => 'Cotización actualizada exitosamente',
                'data' => new QuotationResource($quotation->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'server_error',
                'message' => 'Error al actualizar la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add item to quotation
     */
    public function addItem(AddItemRequest $request, Quotation $quotation): JsonResponse
    {
        if ($quotation->status !== 'draft') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden agregar items a cotizaciones en borrador',
            ], 422);
        }

        try {
            $detail = $this->quotationService->addItem($quotation, $request->validated());
            $this->quotationService->recalculateTotals($quotation);

            return response()->json([
                'message' => 'Producto agregado exitosamente',
                'data' => new QuotationResource($quotation->fresh(['details'])),
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'error' => 'insufficient_stock',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'server_error',
                'message' => 'Error al agregar el producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove item from quotation
     */
    public function removeItem(Quotation $quotation, int $detailId): JsonResponse
    {
        if ($quotation->status !== 'draft') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden eliminar items de cotizaciones en borrador',
            ], 422);
        }

        $detail = $quotation->details()->find($detailId);

        if (!$detail) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Item no encontrado',
            ], 404);
        }

        $detail->delete();
        $this->quotationService->recalculateTotals($quotation);

        return response()->json([
            'message' => 'Producto eliminado exitosamente',
            'data' => new QuotationResource($quotation->fresh(['details'])),
        ]);
    }

    /**
     * Send quotation by email/WhatsApp
     */
    public function send(Request $request, Quotation $quotation): JsonResponse
    {
        $request->validate([
            'method' => 'required|in:email,whatsapp',
            'email' => 'required_if:method,email|email',
            'phone' => 'required_if:method,whatsapp',
        ]);

        // TODO: Implementar lógica de envío
        // - Generar PDF si no existe
        // - Enviar por email o WhatsApp
        // - Actualizar sent_at, sent_to_email

        $quotation->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_to_email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Cotización enviada exitosamente',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Change quotation status
     */
    public function changeStatus(Request $request, Quotation $quotation): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired,converted',
            'notes' => 'nullable|string',
        ]);

        $quotation->update(['status' => $request->status]);

        // Registrar en historial
        $quotation->statusHistory()->create([
            'status' => $request->status,
            'user_id' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Estado actualizado exitosamente',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Convert quotation to sale
     */
    public function convertToSale(Quotation $quotation): JsonResponse
    {
        if ($quotation->status !== 'accepted') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden convertir cotizaciones aceptadas',
            ], 422);
        }

        // TODO: Implementar lógica de conversión a venta
        // - Crear Sale
        // - Crear SaleDetails
        // - Actualizar inventario
        // - Generar órdenes de compra si hay items de proveedor

        return response()->json([
            'message' => 'Cotización convertida a venta exitosamente',
        ]);
    }

    /**
     * Delete quotation (soft delete)
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        if ($quotation->status !== 'draft') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden eliminar cotizaciones en borrador',
            ], 422);
        }

        $quotation->delete();

        return response()->json([
            'message' => 'Cotización eliminada exitosamente',
        ]);
    }

    /**
     * Get available suppliers for a product
     */
    public function getProductSuppliers(int $productId): JsonResponse
    {
        $suppliers = SupplierProduct::where('product_id', $productId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->with('supplier')
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'data' => SupplierProductResource::collection($suppliers),
        ]);
    }

    /**
     * Get quotation statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $userId = $request->user_id ?? $request->user()->id;

        $stats = [
            'total' => Quotation::where('user_id', $userId)->count(),
            'draft' => Quotation::where('user_id', $userId)->where('status', 'draft')->count(),
            'sent' => Quotation::where('user_id', $userId)->where('status', 'sent')->count(),
            'accepted' => Quotation::where('user_id', $userId)->where('status', 'accepted')->count(),
            'converted' => Quotation::where('user_id', $userId)->where('status', 'converted')->count(),
            'total_amount' => Quotation::where('user_id', $userId)->sum('total'),
            'total_margin' => Quotation::where('user_id', $userId)->sum('total_margin'),
            'pending_commission' => Quotation::where('user_id', $userId)
                ->where('commission_paid', false)
                ->sum('commission_amount'),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Update existing item in quotation
     */
    public function updateItem(UpdateItemRequest $request, Quotation $quotation, int $detailId): JsonResponse
    {
        if ($quotation->status !== 'draft') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden actualizar items de cotizaciones en borrador',
            ], 422);
        }

        $detail = $quotation->details()->find($detailId);

        if (!$detail) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Item no encontrado',
            ], 404);
        }

        try {
            $detail->update($request->validated());

            // Recalcular márgenes si cambió cantidad o precio
            if ($request->has(['quantity', 'unit_price'])) {
                $margins = app(MarginCalculatorService::class)->calculate($detail);
                $detail->update($margins);

                // Recalcular subtotal, tax, total
                $subtotal = ($detail->unit_price * $detail->quantity) - ($detail->discount ?? 0);
                $taxAmount = $subtotal * 0.18;
                $total = $subtotal + $taxAmount;

                $detail->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);
            }

            $this->quotationService->recalculateTotals($quotation);

            return response()->json([
                'message' => 'Item actualizado exitosamente',
                'data' => new QuotationResource($quotation->fresh(['details'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'server_error',
                'message' => 'Error al actualizar el item: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update only quantity of item
     */
    public function updateItemQuantity(Request $request, Quotation $quotation, int $detailId): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($quotation->status !== 'draft') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden actualizar items de cotizaciones en borrador',
            ], 422);
        }

        $detail = $quotation->details()->find($detailId);

        if (!$detail) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Item no encontrado',
            ], 404);
        }

        try {
            $detail->update(['quantity' => $request->quantity]);

            // Recalcular márgenes y totales
            $margins = app(MarginCalculatorService::class)->calculate($detail);
            $detail->update($margins);

            $subtotal = ($detail->unit_price * $detail->quantity) - ($detail->discount ?? 0);
            $taxAmount = $subtotal * 0.18;
            $total = $subtotal + $taxAmount;

            $detail->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            $this->quotationService->recalculateTotals($quotation);

            return response()->json([
                'message' => 'Cantidad actualizada exitosamente',
                'data' => new QuotationResource($quotation->fresh(['details'])),
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'error' => 'insufficient_stock',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resend quotation
     */
    public function resend(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorize('send', $quotation);

        $request->validate([
            'method' => 'required|in:email,whatsapp',
            'email' => 'required_if:method,email|email',
            'phone' => 'required_if:method,whatsapp',
        ]);

        // TODO: Implementar lógica de reenvío

        $quotation->update([
            'sent_at' => now(),
            'sent_to_email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Cotización reenviada exitosamente',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Generate/regenerate PDF
     */
    public function generatePdf(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        if (!auth()->user()->can('quotations.generate-pdf')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No tiene permisos para generar PDFs',
            ], 403);
        }

        // TODO: Implementar generación de PDF

        return response()->json([
            'message' => 'PDF generado exitosamente',
            'pdf_url' => $quotation->pdf_path ? url($quotation->pdf_path) : null,
        ]);
    }

    /**
     * Download PDF
     */
    public function downloadPdf(Quotation $quotation)
    {
        $this->authorize('view', $quotation);

        if (!$quotation->pdf_path || !file_exists(storage_path('app/' . $quotation->pdf_path))) {
            return response()->json([
                'error' => 'pdf_not_found',
                'message' => 'El PDF no existe o no ha sido generado',
            ], 404);
        }

        return response()->download(
            storage_path('app/' . $quotation->pdf_path),
            "Cotizacion-{$quotation->quotation_code}.pdf"
        );
    }

    /**
     * Accept quotation
     */
    public function accept(Quotation $quotation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->can('quotations.accept')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No tiene permisos para aceptar cotizaciones',
            ], 403);
        }

        $quotation->markAsAccepted();

        // Registrar en historial
        $quotation->statusHistory()->create([
            'status' => 'accepted',
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Cotización aceptada exitosamente',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Reject quotation
     */
    public function reject(Request $request, Quotation $quotation): JsonResponse
    {
        if (!auth()->user()->can('quotations.reject')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No tiene permisos para rechazar cotizaciones',
            ], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $quotation->markAsRejected();

        // Registrar en historial
        $quotation->statusHistory()->create([
            'status' => 'rejected',
            'user_id' => Auth::id(),
            'notes' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Cotización rechazada',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Expire quotation
     */
    public function expire(Quotation $quotation): JsonResponse
    {
        if (!auth()->user()->can('quotations.expire')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No tiene permisos para expirar cotizaciones',
            ], 403);
        }

        $quotation->markAsExpired();

        return response()->json([
            'message' => 'Cotización marcada como expirada',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Pay commission
     */
    public function payCommission(Quotation $quotation): JsonResponse
    {
        $this->authorize('payCommission', $quotation);

        if ($quotation->commission_paid) {
            return response()->json([
                'error' => 'already_paid',
                'message' => 'La comisión ya fue marcada como pagada',
            ], 422);
        }

        $quotation->payCommission();

        return response()->json([
            'message' => 'Comisión marcada como pagada',
            'data' => new QuotationResource($quotation),
        ]);
    }

    /**
     * Statistics by seller
     */
    public function statisticsBySeller(Request $request): JsonResponse
    {
        if (!auth()->user()->can('quotations.statistics.by-seller')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No tiene permisos para ver estadísticas por vendedor',
            ], 403);
        }

        $stats = User::role('vendor')
            ->withCount([
                'quotations as total_quotations',
                'quotations as accepted_quotations' => fn($q) => $q->where('status', 'accepted'),
                'quotations as converted_quotations' => fn($q) => $q->where('status', 'converted'),
            ])
            ->with(['quotations' => function ($q) {
                $q->selectRaw('user_id, SUM(total) as total_amount, SUM(total_margin) as total_margin, SUM(commission_amount) as total_commission');
                $q->groupBy('user_id');
            }])
            ->get();

        return response()->json(['data' => $stats]);
    }

    /**
     * Pending commissions report
     */
    public function pendingCommissions(Request $request): JsonResponse
    {
        if (!auth()->user()->can('quotations.reports.commissions')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'No tiene permisos para ver reportes de comisiones',
            ], 403);
        }

        $quotations = Quotation::where('commission_paid', false)
            ->where('status', 'converted')
            ->with(['user', 'customer'])
            ->get();

        return response()->json([
            'data' => QuotationResource::collection($quotations),
            'summary' => [
                'total_pending' => $quotations->sum('commission_amount'),
                'count' => $quotations->count(),
            ],
        ]);
    }

    /**
     * Expiring soon alert
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);

        $quotations = Quotation::whereIn('status', ['draft', 'sent'])
            ->where('valid_until', '>', now())
            ->where('valid_until', '<=', now()->addDays($days))
            ->with(['customer', 'user'])
            ->get();

        return response()->json([
            'data' => QuotationResource::collection($quotations),
            'count' => $quotations->count(),
        ]);
    }

    /**
     * Status history
     */
    public function statusHistory(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        $history = $quotation->statusHistory()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $history]);
    }

    /**
     * Check stock
     */
    public function checkStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $results = [];

        foreach ($validated['items'] as $item) {
            $inventory = Inventory::where('product_id', $item['product_id'])
                ->where('warehouse_id', $item['warehouse_id'])
                ->first();

            $results[] = [
                'product_id' => $item['product_id'],
                'warehouse_id' => $item['warehouse_id'],
                'requested_quantity' => $item['quantity'],
                'available_stock' => $inventory?->available_stock ?? 0,
                'sufficient' => ($inventory?->available_stock ?? 0) >= $item['quantity'],
            ];
        }

        return response()->json(['data' => $results]);
    }

    /**
     * Duplicate quotation
     */
    public function duplicate(Quotation $quotation): JsonResponse
    {
        $this->authorize('create', Quotation::class);

        $newQuotation = $quotation->replicate();
        $newQuotation->quotation_code = $this->generateCode();
        $newQuotation->quotation_date = now();
        $newQuotation->status = 'draft';
        $newQuotation->sent_at = null;
        $newQuotation->converted_at = null;
        $newQuotation->converted_sale_id = null;
        $newQuotation->save();

        // Duplicar items
        foreach ($quotation->details as $detail) {
            $newDetail = $detail->replicate();
            $newDetail->quotation_id = $newQuotation->id;
            $newDetail->save();
        }

        return response()->json([
            'message' => 'Cotización duplicada exitosamente',
            'data' => new QuotationResource($newQuotation->load('details')),
        ]);
    }

    /**
     * Calculate totals (preview)
     */
    public function calculateTotals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'packaging_cost' => 'nullable|numeric|min:0',
            'assembly_cost' => 'nullable|numeric|min:0',
        ]);

        $subtotal = 0;
        $tax = 0;

        foreach ($validated['items'] as $item) {
            $itemSubtotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0);
            $itemTax = $itemSubtotal * 0.18;

            $subtotal += $itemSubtotal;
            $tax += $itemTax;
        }

        $total = $subtotal + $tax +
            ($validated['shipping_cost'] ?? 0) +
            ($validated['packaging_cost'] ?? 0) +
            ($validated['assembly_cost'] ?? 0);

        return response()->json([
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
        ]);
    }

    private function generateCode(): string
    {
        $prefix = 'COT';
        $year = now()->year;
        $lastNumber = Quotation::whereYear('created_at', $year)
            ->max('quotation_code');

        if ($lastNumber) {
            $number = (int) substr($lastNumber, -6) + 1;
        } else {
            $number = 1;
        }

        return $prefix . '-' . $year . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validar disponibilidad y precios actuales de una cotización
     * Útil cuando se quiere reactivar una cotización vencida o revisar una antigua
     */
    public function validateAvailability(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        $isExpired = $quotation->valid_until < now();
        $unavailableItems = [];
        $priceChanges = [];
        $stockWarnings = [];

        foreach ($quotation->details as $detail) {
            $itemValidation = [
                'detail_id' => $detail->id,
                'product_name' => $detail->product_name,
                'original_price' => (float) $detail->unit_price,
                'original_stock' => $detail->available_stock,
            ];

            // ============================================
            // VALIDAR PRODUCTOS DE ALMACÉN
            // ============================================
            if ($detail->source_type === 'warehouse') {
                $inventory = Inventory::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $detail->warehouse_id)
                    ->first();

                if (!$inventory) {
                    $unavailableItems[] = array_merge($itemValidation, [
                        'reason' => 'Producto ya no existe en este almacén',
                        'current_stock' => 0,
                        'is_available' => false,
                    ]);
                    continue;
                }

                $currentStock = $inventory->available_stock;
                $currentPrice = $inventory->sale_price;

                // Verificar stock suficiente
                if ($currentStock < $detail->quantity) {
                    $stockWarnings[] = array_merge($itemValidation, [
                        'current_stock' => $currentStock,
                        'required_quantity' => $detail->quantity,
                        'shortage' => $detail->quantity - $currentStock,
                        'message' => "Stock insuficiente. Solo hay {$currentStock} disponibles, necesita {$detail->quantity}",
                    ]);
                }

                // Verificar cambio de precio (solo si está vencida)
                if ($isExpired && $currentPrice != $detail->unit_price) {
                    $priceChanges[] = array_merge($itemValidation, [
                        'current_price' => (float) $currentPrice,
                        'price_difference' => (float) ($currentPrice - $detail->unit_price),
                        'price_change_percentage' => round((($currentPrice - $detail->unit_price) / $detail->unit_price) * 100, 2),
                    ]);
                }
            }

            // ============================================
            // VALIDAR PRODUCTOS DE PROVEEDOR
            // ============================================
            elseif ($detail->source_type === 'supplier') {

                // Si tiene supplier_product_id específico
                if ($detail->supplier_product_id) {
                    $supplierProduct = SupplierProduct::find($detail->supplier_product_id);

                    if (!$supplierProduct || !$supplierProduct->is_available || !$supplierProduct->is_active) {
                        $unavailableItems[] = array_merge($itemValidation, [
                            'reason' => 'Producto ya no disponible con este proveedor',
                            'supplier_name' => $detail->supplier?->business_name,
                            'is_available' => false,
                        ]);
                        continue;
                    }

                    // Verificar cambio de precio
                    if ($isExpired && $supplierProduct->supplier_price != $detail->purchase_price) {
                        $priceChanges[] = array_merge($itemValidation, [
                            'current_price' => (float) $supplierProduct->supplier_price,
                            'price_difference' => (float) ($supplierProduct->supplier_price - $detail->purchase_price),
                            'supplier_name' => $detail->supplier?->business_name,
                        ]);
                    }
                }
                // Si solo tiene supplier_id, buscar alternativas
                else {
                    $alternativeSuppliers = SupplierProduct::where('product_id', $detail->product_id)
                        ->where('is_active', true)
                        ->where('is_available', true)
                        ->with('supplier')
                        ->orderBy('priority', 'desc')
                        ->orderBy('supplier_price', 'asc')
                        ->get();

                    if ($alternativeSuppliers->isEmpty()) {
                        $unavailableItems[] = array_merge($itemValidation, [
                            'reason' => 'No hay proveedores disponibles para este producto',
                            'is_available' => false,
                        ]);
                    } else {
                        // Sugerir el mejor proveedor disponible
                        $itemValidation['suggested_suppliers'] = $alternativeSuppliers->map(function ($sp) {
                            return [
                                'supplier_id' => $sp->supplier_id,
                                'supplier_name' => $sp->supplier->business_name,
                                'price' => (float) $sp->supplier_price,
                                'lead_time_days' => $sp->lead_time_days,
                                'priority' => $sp->priority,
                            ];
                        });
                    }
                }
            }
        }

        // ============================================
        // CALCULAR NUEVOS TOTALES SI HAY CAMBIOS
        // ============================================
        $newTotals = null;
        if ($isExpired && !empty($priceChanges)) {
            $newTotals = $this->calculateNewTotals($quotation, $priceChanges);
        }

        // ============================================
        // DETERMINAR SI SE PUEDE USAR LA COTIZACIÓN
        // ============================================
        $canBeUsed = empty($unavailableItems);
        $needsRecalculation = $isExpired && (!empty($priceChanges) || !empty($stockWarnings));

        return response()->json([
            'quotation_id' => $quotation->id,
            'quotation_code' => $quotation->quotation_code,
            'is_expired' => $isExpired,
            'valid_until' => $quotation->valid_until,
            'days_expired' => $isExpired ? now()->diffInDays($quotation->valid_until) : null,
            'can_be_used' => $canBeUsed,
            'needs_recalculation' => $needsRecalculation,

            'validation_summary' => [
                'total_items' => $quotation->details->count(),
                'unavailable_items' => count($unavailableItems),
                'price_changes' => count($priceChanges),
                'stock_warnings' => count($stockWarnings),
            ],

            'unavailable_items' => $unavailableItems,
            'price_changes' => $priceChanges,
            'stock_warnings' => $stockWarnings,

            'current_totals' => [
                'subtotal' => (float) $quotation->subtotal,
                'tax' => (float) $quotation->tax,
                'total' => (float) $quotation->total,
                'margin' => (float) $quotation->total_margin,
                'margin_percentage' => (float) $quotation->margin_percentage,
            ],

            'new_totals' => $newTotals,

            'actions' => [
                'can_convert_to_sale' => $canBeUsed && !$needsRecalculation,
                'should_recalculate' => $needsRecalculation,
                'should_notify_customer' => !empty($priceChanges) || !empty($unavailableItems),
            ],
        ]);
    }

    /**
     * Calcular nuevos totales con precios actualizados
     */
    private function calculateNewTotals(Quotation $quotation, array $priceChanges): array
    {
        $newSubtotal = $quotation->subtotal;
        $priceChangesMap = collect($priceChanges)->keyBy('detail_id');

        foreach ($quotation->details as $detail) {
            if (isset($priceChangesMap[$detail->id])) {
                $change = $priceChangesMap[$detail->id];
                $oldItemTotal = $detail->unit_price * $detail->quantity;
                $newItemTotal = $change['current_price'] * $detail->quantity;
                $newSubtotal += ($newItemTotal - $oldItemTotal);
            }
        }

        $newTax = $newSubtotal * 0.18;
        $newTotal = $newSubtotal + $newTax +
            ($quotation->shipping_cost ?? 0) +
            ($quotation->packaging_cost ?? 0) +
            ($quotation->assembly_cost ?? 0);

        return [
            'subtotal' => round($newSubtotal, 2),
            'tax' => round($newTax, 2),
            'total' => round($newTotal, 2),
            'difference' => round($newTotal - $quotation->total, 2),
            'difference_percentage' => round((($newTotal - $quotation->total) / $quotation->total) * 100, 2),
        ];
    }

    /**
     * Recalcular cotización con precios actuales
     * Crea una nueva versión de la cotización con precios actualizados
     */
    public function recalculateWithCurrentPrices(Quotation $quotation): JsonResponse
    {
        $this->authorize('update', $quotation);

        if ($quotation->status !== 'draft' && !$quotation->isExpired()) {
            return response()->json([
                'error' => 'invalid_operation',
                'message' => 'Solo se pueden recalcular cotizaciones en borrador o vencidas',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updatedItems = [];
            $unavailableItems = [];

            foreach ($quotation->details as $detail) {
                if ($detail->source_type === 'warehouse') {
                    $inventory = Inventory::where('product_id', $detail->product_id)
                        ->where('warehouse_id', $detail->warehouse_id)
                        ->first();

                    if (!$inventory || $inventory->available_stock < $detail->quantity) {
                        $unavailableItems[] = $detail->product_name;
                        continue;
                    }

                    // Actualizar precio y costos
                    $detail->update([
                        'unit_price' => $inventory->sale_price,
                        'purchase_price' => $detail->product->distribution_price ?? 0,
                        'available_stock' => $inventory->available_stock,
                        'in_stock' => true,
                    ]);

                    $updatedItems[] = $detail->id;
                } elseif ($detail->source_type === 'supplier' && $detail->supplier_product_id) {
                    $supplierProduct = SupplierProduct::find($detail->supplier_product_id);

                    if (!$supplierProduct || !$supplierProduct->is_available) {
                        $unavailableItems[] = $detail->product_name;
                        continue;
                    }

                    $detail->update([
                        'purchase_price' => $supplierProduct->supplier_price,
                        'supplier_price' => $supplierProduct->supplier_price,
                    ]);

                    $updatedItems[] = $detail->id;
                }
            }

            // Recalcular márgenes y totales
            foreach ($quotation->details()->whereIn('id', $updatedItems)->get() as $detail) {
                $margins = app(MarginCalculatorService::class)->calculate($detail);
                $detail->update($margins);

                $subtotal = ($detail->unit_price * $detail->quantity) - ($detail->discount ?? 0);
                $taxAmount = $subtotal * 0.18;
                $total = $subtotal + $taxAmount;

                $detail->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);
            }

            // Actualizar fecha de validez
            $quotation->update([
                'valid_until' => now()->addDays(15)->toDateString(),
                'quotation_date' => now(),
            ]);

            $this->quotationService->recalculateTotals($quotation);

            DB::commit();

            return response()->json([
                'message' => 'Cotización recalculada exitosamente',
                'data' => new QuotationResource($quotation->fresh(['details'])),
                'summary' => [
                    'updated_items' => count($updatedItems),
                    'unavailable_items' => $unavailableItems,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'recalculation_failed',
                'message' => 'Error al recalcular la cotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validar si un precio propuesto cumple con el margen mínimo
     * Útil para el frontend antes de agregar un producto
     */
    public function validatePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'proposed_price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
        ]);

        $validation = app(MarginCalculatorService::class)->validateProposedPrice(
            $validated['proposed_price'],
            $validated['cost'],
            $validated['product_id']
        );

        return response()->json([
            'data' => $validation,
            'message' => $validation['is_valid']
                ? 'El precio cumple con el margen mínimo'
                : 'El precio no cumple con el margen mínimo requerido',
        ], $validation['is_valid'] ? 200 : 422);
    }

    /**
     * Sugerir precio según margen objetivo de la categoría
     */
    public function suggestPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'cost' => 'required|numeric|min:0',
            'target_margin' => 'nullable|numeric|min:0', // Si no se envía, usa el de la categoría
        ]);

        $product = \App\Models\Product::with('category')->find($validated['product_id']);

        // Usar margen objetivo o el normal de la categoría
        $targetMargin = $validated['target_margin']
            ?? $product->category?->normal_margin_percentage
            ?? app(SettingService::class)->get('margins', 'default_margin_percentage', 20);

        $minMargin = $product->category?->min_margin_percentage
            ?? app(SettingService::class)->get('margins', 'min_margin_percentage', 10);

        $suggestedPrice = app(MarginCalculatorService::class)->calculateSuggestedPrice(
            $validated['cost'],
            $targetMargin
        );

        $minPrice = app(MarginCalculatorService::class)->calculateSuggestedPrice(
            $validated['cost'],
            $minMargin
        );

        return response()->json([
            'data' => [
                'product_id' => $validated['product_id'],
                'product_name' => $product->primary_name,
                'category' => $product->category?->name,
                'cost' => round($validated['cost'], 2),
                'target_margin' => round($targetMargin, 2),
                'min_margin' => round($minMargin, 2),
                'suggested_price' => round($suggestedPrice, 2),
                'min_price' => round($minPrice, 2),
                'margin_range' => [
                    'min' => round($minPrice, 2),
                    'recommended' => round($suggestedPrice, 2),
                    'max' => null, // Sin límite máximo
                ],
            ],
        ]);
    }

    /**
     * Obtener resumen de márgenes de una cotización
     */
    public function marginsBreakdown(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        $breakdown = [
            'quotation_id' => $quotation->id,
            'quotation_code' => $quotation->quotation_code,
            'total_margin' => (float) $quotation->total_margin,
            'margin_percentage' => (float) $quotation->margin_percentage,
            'items' => [],
            'summary' => [
                'total_cost' => 0,
                'total_price' => 0,
                'items_count' => $quotation->details->count(),
                'items_with_low_margin' => 0,
            ],
        ];

        foreach ($quotation->details as $detail) {
            $product = $detail->product;
            $categoryMinMargin = $product?->category?->min_margin_percentage ?? 10;
            $isLowMargin = $detail->margin_percentage < $categoryMinMargin;

            $breakdown['items'][] = [
                'detail_id' => $detail->id,
                'product_name' => $detail->product_name,
                'quantity' => $detail->quantity,
                'unit_cost' => (float) $detail->unit_cost,
                'unit_price' => (float) $detail->unit_price,
                'unit_margin' => (float) $detail->unit_margin,
                'total_cost' => (float) $detail->total_cost,
                'total_price' => (float) $detail->subtotal,
                'total_margin' => (float) $detail->total_margin,
                'margin_percentage' => (float) $detail->margin_percentage,
                'category_min_margin' => $categoryMinMargin,
                'is_low_margin' => $isLowMargin,
                'margin_status' => $isLowMargin ? 'low' : 'ok',
            ];

            $breakdown['summary']['total_cost'] += $detail->total_cost;
            $breakdown['summary']['total_price'] += $detail->subtotal;

            if ($isLowMargin) {
                $breakdown['summary']['items_with_low_margin']++;
            }
        }

        return response()->json(['data' => $breakdown]);
    }

    // ============================================================================
    // QUOTATION BUILDER - Filtrado de productos
    // ============================================================================

    /**
     * Obtener productos para el constructor de cotizaciones con filtros
     * 
     * Filtros disponibles:
     * - warehouse_id (required): Filtrar por almacén con stock
     * - supplier_id (optional): Filtrar por productos del proveedor
     * - family_id (optional): Filtrar por familia de categoría
     * - search (optional): Búsqueda por SKU/nombre/marca
     */
    public function getProducts(GetQuotationProductsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        
        $products = $this->quotationService->getProductsForQuotation($filters);
        $globalStats = $this->quotationService->getBuilderStats($filters['warehouse_id']);
        
        $collection = (new QuotationProductCollection($products))
            ->setFilters($filters)
            ->setGlobalStats($globalStats);
        
        return response()->json([
            'success' => true,
            'message' => 'Productos obtenidos exitosamente',
            ...$collection->toResponse($request)->getData(true),
        ]);
    }

    /**
     * Obtener opciones de filtro para el builder de cotizaciones
     * 
     * Retorna:
     * - warehouses: Lista de almacenes activos
     * - suppliers: Proveedores con productos asociados
     */
    public function getFilterOptions(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        
        $warehouses = Warehouse::where('is_active', true)
            ->select('id', 'name', 'is_main')
            ->orderBy('is_main', 'desc')
            ->orderBy('name')
            ->get();
        
        $suppliers = $this->quotationService->getSuppliersWithProducts($warehouseId);
        
        return response()->json([
            'success' => true,
            'message' => 'Opciones de filtro obtenidas exitosamente',
            'data' => [
                'warehouses' => $warehouses,
                'suppliers' => $suppliers,
            ],
        ]);
    }
}

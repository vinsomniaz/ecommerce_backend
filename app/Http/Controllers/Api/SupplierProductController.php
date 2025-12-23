<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierProduct;
use App\Http\Resources\SupplierProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupplierProduct::with(['supplier', 'product'])
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->is_active, fn($q) => $q->where('is_active', $request->is_active))
            ->when($request->is_available, fn($q) => $q->where('is_available', $request->is_available))
            ->when($request->currency, fn($q) => $q->where('currency', $request->currency))
            ->orderBy('priority', 'desc');

        $supplierProducts = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => SupplierProductResource::collection($supplierProducts->items()),
            'meta' => [
                'current_page' => $supplierProducts->currentPage(),
                'last_page' => $supplierProducts->lastPage(),
                'per_page' => $supplierProducts->perPage(),
                'total' => $supplierProducts->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:entities,id',
            'product_id' => 'nullable|exists:products,id',
            'supplier_sku' => 'required|string|max:160',
            'supplier_name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:100',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'required|in:PEN,USD,EUR',
            'available_stock' => 'nullable|integer|min:0',
            'delivery_days' => 'nullable|integer|min:0',
            'min_order_quantity' => 'nullable|integer|min:1',
            'priority' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $supplierProduct = SupplierProduct::create($validated);

        return response()->json([
            'message' => 'Producto de proveedor creado exitosamente',
            'data' => new SupplierProductResource($supplierProduct),
        ], 201);
    }

    public function show(SupplierProduct $supplierProduct): JsonResponse
    {
        $supplierProduct->load(['supplier', 'product']);

        return response()->json([
            'data' => new SupplierProductResource($supplierProduct),
        ]);
    }

    public function update(Request $request, SupplierProduct $supplierProduct): JsonResponse
    {
        $validated = $request->validate([
            'supplier_sku' => 'nullable|string|max:160',
            'supplier_name' => 'nullable|string|max:255',
            'product_id' => 'nullable|exists:products,id', // Vincular a producto interno
            'category_id' => 'nullable|exists:categories,id', // Override manual de categoría
            'purchase_price' => 'sometimes|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|in:PEN,USD,EUR',
            'available_stock' => 'nullable|integer|min:0',
            'is_available' => 'sometimes|boolean',
            'delivery_days' => 'nullable|integer|min:0',
            'min_order_quantity' => 'nullable|integer|min:1',
            'priority' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $supplierProduct->update($validated);

        return response()->json([
            'message' => 'Producto de proveedor actualizado exitosamente',
            'data' => new SupplierProductResource($supplierProduct->fresh()),
        ]);
    }

    public function destroy(SupplierProduct $supplierProduct): JsonResponse
    {
        $supplierProduct->delete();

        return response()->json([
            'message' => 'Producto de proveedor eliminado exitosamente',
        ]);
    }

    public function byProduct(int $productId): JsonResponse
    {
        $suppliers = SupplierProduct::where('product_id', $productId)
            ->with('supplier')
            ->active()
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'data' => SupplierProductResource::collection($suppliers),
        ]);
    }

    public function bySupplier(int $supplierId): JsonResponse
    {
        $products = SupplierProduct::where('supplier_id', $supplierId)
            ->with('product')
            ->active()
            ->get();

        return response()->json([
            'data' => SupplierProductResource::collection($products),
        ]);
    }

    public function comparePrices(int $productId): JsonResponse
    {
        $suppliers = SupplierProduct::where('product_id', $productId)
            ->with('supplier')
            ->active()
            ->available()
            ->orderBy('purchase_price', 'asc')
            ->get();

        return response()->json([
            'data' => SupplierProductResource::collection($suppliers),
            'best_price' => $suppliers->first()?->purchase_price,
            'highest_price' => $suppliers->last()?->purchase_price,
            'average_price' => $suppliers->avg('purchase_price'),
        ]);
    }

    public function bulkUpdatePrices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|exists:supplier_products,id',
            'updates.*.purchase_price' => 'required|numeric|min:0',
        ]);

        $updated = 0;
        foreach ($validated['updates'] as $update) {
            SupplierProduct::find($update['id'])->update([
                'purchase_price' => $update['purchase_price'],
                'price_updated_at' => now(),
            ]);
            $updated++;
        }

        return response()->json([
            'message' => "Se actualizaron {$updated} precios exitosamente",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Asignación masiva de categoría a productos sin categoría del proveedor
     */
    public function bulkUpdateCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:supplier_products,id',
            'category_id' => 'required|exists:categories,id',
        ]);

        $updated = SupplierProduct::whereIn('id', $validated['product_ids'])
            ->update(['category_id' => $validated['category_id']]);

        return response()->json([
            'success' => true,
            'message' => "Se asignó categoría a {$updated} productos exitosamente",
            'data' => [
                'updated_count' => $updated,
                'category_id' => $validated['category_id'],
            ],
        ]);
    }

    /**
     * Productos sin categorizar o vincular
     */
    public function uncategorized(Request $request): JsonResponse
    {
        $query = SupplierProduct::with(['supplier', 'product'])
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->where(function ($q) {
                // Productos que necesitan atención
                $q->whereNull('product_id'); // No vinculados a producto interno
            });

        // Filtrar por estado de asignación de categoría
        if ($request->has('has_category_id') && $request->has_category_id === 'true') {
            $query->whereNotNull('category_id'); // Solo productos YA asignados
        } else {
            $query->whereNull('category_id'); // Solo productos PENDIENTES
        }

        $query->active()->latest();

        $products = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => SupplierProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Estadísticas de productos de proveedores
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = SupplierProduct::query();

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $total = $query->count();
        $active = (clone $query)->where('is_active', true)->count();
        $linked = (clone $query)->whereNotNull('product_id')->count();
        $unlinked = (clone $query)->whereNull('product_id')->count();
        $available = (clone $query)->where('is_available', true)->count();
        $withCategory = (clone $query)->whereNotNull('supplier_category')->count();
        $withoutCategory = (clone $query)->whereNull('supplier_category')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $total - $active,
                'linked_to_products' => $linked,
                'unlinked' => $unlinked,
                'available' => $available,
                'unavailable' => $total - $available,
                'with_supplier_category' => $withCategory,
                'without_supplier_category' => $withoutCategory,
                'linking_rate' => $total > 0 ? round(($linked / $total) * 100, 2) : 0,
            ],
        ]);
    }
}

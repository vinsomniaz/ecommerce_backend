<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierProduct;
use App\Http\Resources\SupplierProductResource;
use App\Http\Resources\SupplierProductCollection;
use App\Services\SupplierProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierProductController extends Controller
{
    public function __construct(
        private SupplierProductService $supplierProductService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->supplierProductService->getProducts($request);

        return response()->json([
            'success' => true,
            'message' => 'Productos de proveedores obtenidos correctamente',
            'data' => SupplierProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
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
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
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
        $suppliers = $this->supplierProductService->getByProduct($productId);

        return response()->json([
            'data' => SupplierProductResource::collection($suppliers),
        ]);
    }

    public function bySupplier(int $supplierId): JsonResponse
    {
        $products = $this->supplierProductService->getBySupplier($supplierId);

        return response()->json([
            'data' => SupplierProductResource::collection($products),
        ]);
    }

    public function comparePrices(int $productId): JsonResponse
    {
        $suppliers = $this->supplierProductService->comparePrices($productId);

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

        $updated = $this->supplierProductService->bulkUpdatePrices($validated['updates']);

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

        $updated = $this->supplierProductService->bulkUpdateCategories(
            $validated['product_ids'],
            $validated['category_id']
        );

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
     * Productos sin categorizar o vincular (con caché y estadísticas)
     */
    public function uncategorized(Request $request): JsonResponse
    {
        $products = $this->supplierProductService->getUncategorizedProducts($request);
        $collection = new SupplierProductCollection($products);

        return response()->json([
            'success' => true,
            'message' => 'Productos sin categoría obtenidos correctamente',
            'data' => $collection->toArray($request)['data'],
            'meta' => $collection->with($request)['meta'],
        ]);
    }

    /**
     * Estadísticas de productos de proveedores
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->supplierProductService->getStatistics($request->supplier_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Productos sin categorizar agrupados por category_suggested
     */
    public function uncategorizedGrouped(Request $request): JsonResponse
    {
        $supplierId = $request->query('supplier_id');
        $onlyPending = $request->query('only_pending', 'true') === 'true';

        $result = $this->supplierProductService->getGroupedUncategorizedProducts(
            $supplierId ? (int) $supplierId : null,
            $onlyPending
        );

        return response()->json([
            'success' => true,
            'message' => 'Productos agrupados por categoría sugerida obtenidos correctamente',
            'data' => $result['groups'],
            'meta' => [
                'stats' => $result['stats'],
            ],
        ]);
    }
}

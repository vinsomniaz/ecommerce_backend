<?php

namespace App\Services;

use App\Models\SupplierProduct;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{Cache, Log};

class SupplierProductService
{
    /**
     * Obtiene productos no categorizados con filtros y paginación
     */
    public function getUncategorizedProducts(Request $request): LengthAwarePaginator
    {
        $supplierId = $request->query('supplier_id');
        $hasCategoryId = $request->has('has_category_id') && $request->has_category_id === 'true';
        $perPage = $request->query('per_page', 15);
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        // Validar columnas de ordenamiento permitidas
        $allowedSortColumns = ['supplier_name', 'purchase_price', 'sale_price', 'available_stock', 'created_at', 'category_suggested'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $query = SupplierProduct::with(['supplier', 'product', 'category'])
            ->whereNull('product_id') // No vinculados a producto interno
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))

            // Búsqueda por texto (nombre, SKU, marca)
            ->when($request->search, function ($q) use ($request) {
                $search = '%' . $request->search . '%';
                $q->where(function ($query) use ($search) {
                    $query->where('supplier_name', 'like', $search)
                        ->orWhere('supplier_sku', 'like', $search)
                        ->orWhere('brand', 'like', $search);
                });
            })

            // Filtro por marca
            ->when($request->brand, fn($q) => $q->where('brand', 'like', '%' . $request->brand . '%'))

            // Filtro por categoría sugerida exacta
            ->when($request->category_suggested, fn($q) => $q->where('category_suggested', $request->category_suggested))

            // Filtro por categoría asignada específica (incluyendo subcategorías)
            ->when($request->category_id, function ($q) use ($request) {
                $category = Category::with('children.children')->find($request->category_id);
                if ($category) {
                    $categoryIds = $category->getAllDescendantIdsWithCache();
                    $q->whereIn('category_id', $categoryIds);
                }
            });

        // Filtrar por estado de asignación de categoría
        if ($hasCategoryId) {
            $query->whereNotNull('category_id'); // Solo productos YA asignados
        } else {
            // PENDIENTES:
            // 1. No tienen categoría asignada (category_id IS NULL)
            // 2. No tienen categoría de proveedor (supplier_category IS NULL)
            // 3. Tienen categoría sugerida (category_suggested IS NOT NULL)
            $query->whereNull('category_id')
                ->whereNull('supplier_category')
                ->whereNotNull('category_suggested');
        }

        return $query->active()->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Obtiene todos los productos de proveedores con filtros avanzados
     */
    public function getProducts(Request $request): LengthAwarePaginator
    {
        $perPage = $request->query('per_page', 15);
        $sortBy = $request->query('sort_by', 'priority');
        $sortOrder = $request->query('sort_order', 'desc');

        // Validar columnas de ordenamiento permitidas
        $allowedSortColumns = ['priority', 'supplier_name', 'purchase_price', 'sale_price', 'available_stock', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'priority';
        }

        $query = SupplierProduct::with(['supplier', 'product', 'category'])
            // Filtros básicos
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            // Filtro por categoría (incluyendo subcategorías)
            ->when($request->category_id, function ($q) use ($request) {
                $category = Category::with('children.children')->find($request->category_id);
                if ($category) {
                    $categoryIds = $category->getAllDescendantIdsWithCache();
                    $q->whereIn('category_id', $categoryIds);
                }
            })
            ->when($request->currency, fn($q) => $q->where('currency', $request->currency))

            // Búsqueda por texto (nombre, SKU, marca)
            ->when($request->search, function ($q) use ($request) {
                $search = '%' . $request->search . '%';
                $q->where(function ($query) use ($search) {
                    $query->where('supplier_name', 'like', $search)
                        ->orWhere('supplier_sku', 'like', $search)
                        ->orWhere('brand', 'like', $search);
                });
            })

            // Filtro por marca
            ->when($request->brand, fn($q) => $q->where('brand', 'like', '%' . $request->brand . '%'))

            // Filtros de estado
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('is_available'), fn($q) => $q->where('is_available', $request->boolean('is_available')))

            // Filtro por vinculación a producto interno
            ->when($request->has('is_linked'), function ($q) use ($request) {
                if ($request->boolean('is_linked')) {
                    $q->whereNotNull('product_id');
                } else {
                    $q->whereNull('product_id');
                }
            })

            // Filtro por categoría asignada
            ->when($request->has('has_category'), function ($q) use ($request) {
                if ($request->boolean('has_category')) {
                    $q->whereNotNull('category_id');
                } else {
                    $q->whereNull('category_id');
                }
            })

            // Filtro por rango de precios
            ->when($request->min_price, fn($q) => $q->where('purchase_price', '>=', $request->min_price))
            ->when($request->max_price, fn($q) => $q->where('purchase_price', '<=', $request->max_price))

            // Filtro por stock
            ->when($request->has('in_stock'), function ($q) use ($request) {
                if ($request->boolean('in_stock')) {
                    $q->where('available_stock', '>', 0);
                } else {
                    $q->where('available_stock', '<=', 0);
                }
            })
            ->when($request->min_stock, fn($q) => $q->where('available_stock', '>=', $request->min_stock))

            // Filtro por categoría sugerida
            ->when($request->category_suggested, fn($q) => $q->where('category_suggested', $request->category_suggested))

            // Ordenamiento
            ->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Obtiene estadísticas de productos no vinculados (para uncategorized)
     */
    public function getUncategorizedStats(?int $supplierId = null): array
    {
        $version = Cache::get('supplier_products_version', 1);
        $statsKey = "supplier_products_stats_uncategorized_v{$version}_" . ($supplierId ?? 'all');

        return Cache::remember($statsKey, now()->addMinutes(30), function () use ($supplierId) {
            // Universo: Productos no vinculados, sin categoría de proveedor (huérfanos), y con sugerencia
            $query = SupplierProduct::query()
                ->whereNull('product_id')
                ->whereNull('supplier_category')
                ->whereNotNull('category_suggested');

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $total = $query->count();

            // Mapped: Dentro de este universo, los que ya tienen category_id manual
            $mapped = (clone $query)->whereNotNull('category_id')->count();

            // Unmapped: Dentro de este universo, los que faltan asignar
            $unmapped = (clone $query)->whereNull('category_id')->count();

            $active = (clone $query)->where('is_active', true)->count();
            $inactive = (clone $query)->where('is_active', false)->count();

            return [
                'total' => $total,
                'mapped' => $mapped,
                'unmapped' => $unmapped,
                'active' => $active,
                'inactive' => $inactive,
                'mapping_rate' => $total > 0 ? round(($mapped / $total) * 100, 2) : 0,
            ];
        });
    }

    /**
     * Obtiene todos los productos no categorizados agrupados por category_suggested
     */
    public function getGroupedUncategorizedProducts(?int $supplierId = null, bool $onlyPending = true): array
    {
        $query = SupplierProduct::with(['supplier'])
            ->whereNull('product_id')
            ->whereNull('supplier_category')
            ->whereNotNull('category_suggested');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Si solo pendientes, excluir los que ya tienen category_id
        if ($onlyPending) {
            $query->whereNull('category_id');
        }

        $products = $query->active()->orderBy('category_suggested')->get();

        // Agrupar por category_suggested
        $grouped = $products->groupBy('category_suggested')->map(function ($items, $category) {
            return $items->map(function ($product) {
                return [
                    'id' => $product->id,
                    'supplier_sku' => $product->supplier_sku,
                    'supplier_name' => $product->supplier_name,
                    'brand' => $product->brand,
                    'image_url' => $product->image_url,
                    'purchase_price' => $product->purchase_price,
                    'sale_price' => $product->sale_price,
                    'currency' => $product->currency,
                    'available_stock' => $product->available_stock,
                    'stock_text' => $product->stock_text,
                    'category_suggested' => $product->category_suggested,
                    'category_id' => $product->category_id,
                    'supplier' => $product->supplier ? [
                        'id' => $product->supplier->id,
                        'business_name' => $product->supplier->business_name,
                        'trade_name' => $product->supplier->trade_name,
                    ] : null,
                ];
            })->values();
        });

        // Estadísticas
        $totalProducts = $products->count();
        $pendingCount = $products->whereNull('category_id')->count();
        $assignedCount = $products->whereNotNull('category_id')->count();
        $totalGroups = $grouped->count();

        return [
            'groups' => $grouped,
            'stats' => [
                'total_products' => $totalProducts,
                'pending_count' => $pendingCount,
                'assigned_count' => $assignedCount,
                'total_groups' => $totalGroups,
            ],
        ];
    }



    /**
     * Estadísticas generales de productos de proveedores
     */
    public function getStatistics(?int $supplierId = null): array
    {
        $query = SupplierProduct::query();

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $total = $query->count();
        $active = (clone $query)->where('is_active', true)->count();
        $linked = (clone $query)->whereNotNull('product_id')->count();
        $unlinked = (clone $query)->whereNull('product_id')->count();
        $available = (clone $query)->where('is_available', true)->count();
        $withCategory = (clone $query)->whereNotNull('supplier_category')->count();
        $withoutCategory = (clone $query)->whereNull('supplier_category')->count();

        return [
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
        ];
    }

    /**
     * Asignación masiva de categoría
     */
    public function bulkUpdateCategories(array $productIds, int $categoryId): int
    {
        $updated = SupplierProduct::whereIn('id', $productIds)
            ->update(['category_id' => $categoryId]);

        // Invalidar caché
        Cache::increment('supplier_products_version');

        Log::info('Asignación masiva de categoría a productos', [
            'category_id' => $categoryId,
            'updated_count' => $updated,
        ]);

        return $updated;
    }

    /**
     * Actualización masiva de precios
     */
    public function bulkUpdatePrices(array $updates): int
    {
        $updated = 0;
        foreach ($updates as $update) {
            SupplierProduct::find($update['id'])?->update([
                'purchase_price' => $update['purchase_price'],
                'price_updated_at' => now(),
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Productos por product_id interno
     */
    public function getByProduct(int $productId)
    {
        return SupplierProduct::where('product_id', $productId)
            ->with('supplier')
            ->active()
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Productos por supplier_id
     */
    public function getBySupplier(int $supplierId)
    {
        return SupplierProduct::where('supplier_id', $supplierId)
            ->with('product')
            ->active()
            ->get();
    }

    /**
     * Comparar precios de un producto
     */
    public function comparePrices(int $productId)
    {
        return SupplierProduct::where('product_id', $productId)
            ->with('supplier')
            ->active()
            ->available()
            ->orderBy('purchase_price', 'asc')
            ->get();
    }
}

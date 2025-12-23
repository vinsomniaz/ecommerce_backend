<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuotationService
{
    public function __construct(
        private SettingService $settingService,
        private MarginCalculatorService $marginCalculator,
        private CommissionCalculatorService $commissionCalculator
    ) {}

    public function create(array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($data, $user) {
            // Crear cotización
            $quotation = Quotation::create([
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'quotation_code' => $this->generateCode(),
                'quotation_date' => now(),
                'valid_until' => $this->calculateValidUntil($data['valid_days'] ?? null),
                'status' => 'draft',
                'currency' => $data['currency'] ?? 'PEN',
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'commission_percentage' => $user->commission_percentage ?? 0,
                // Datos del cliente (snapshot)
                'customer_name' => $data['customer_name'],
                'customer_document' => $data['customer_document'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
            ]);

            // Agregar items
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addItem($quotation, $item);
                }
            }

            // Calcular totales
            $this->recalculateTotals($quotation);

            return $quotation->fresh(['details', 'customer', 'user']);
        });
    }

    public function addItem(Quotation $quotation, array $itemData): QuotationDetail
    {
        $detail = $quotation->details()->create([
            'product_id' => $itemData['product_id'],
            'product_name' => $itemData['product_name'],
            'product_sku' => $itemData['product_sku'] ?? null,
            'product_brand' => $itemData['product_brand'] ?? null,
            'quantity' => $itemData['quantity'],
            'unit_price' => $itemData['unit_price'],
            'discount' => $itemData['discount'] ?? 0,
            'source_type' => $itemData['source_type'] ?? 'warehouse',
            'warehouse_id' => $itemData['warehouse_id'] ?? null,
            'supplier_id' => $itemData['supplier_id'] ?? null,
            'supplier_product_id' => $itemData['supplier_product_id'] ?? null,
            'is_requested_from_supplier' => $itemData['is_requested_from_supplier'] ?? false,
            'purchase_price' => $itemData['purchase_price'] ?? 0,
        ]);

        // Calcular márgenes
        $margins = $this->marginCalculator->calculate($detail);
        $detail->update($margins);

        // Calcular subtotal, tax, total del item
        $this->calculateItemTotals($detail);

        return $detail;
    }

    private function calculateItemTotals(QuotationDetail $detail): void
    {
        $subtotal = ($detail->unit_price * $detail->quantity) - $detail->discount;
        $taxAmount = $subtotal * 0.18; // IGV 18%
        $total = $subtotal + $taxAmount;

        $detail->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    public function recalculateTotals(Quotation $quotation): void
    {
        $quotation->load('details');

        // Sumar totales de items
        $subtotal = $quotation->details->sum('subtotal');
        $tax = $quotation->details->sum('tax_amount');
        $total = $subtotal + $tax +
            ($quotation->shipping_cost ?? 0) +
            ($quotation->packaging_cost ?? 0) +
            ($quotation->assembly_cost ?? 0);

        // Calcular márgenes totales
        $margins = $this->marginCalculator->calculateQuotationTotalMargin($quotation->details);

        // Calcular comisiones
        $quotation->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'total_margin' => $margins['total_margin'],
            'margin_percentage' => $margins['margin_percentage'],
        ]);

        $commission = $this->commissionCalculator->calculate($quotation->fresh());
        $quotation->update([
            'commission_amount' => $commission['commission_amount'],
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

    private function calculateValidUntil(?int $days): string
    {
        $validDays = $days ?? $this->settingService->get('quotations', 'default_validity_days', 15);
        return now()->addDays($validDays)->toDateString();
    }
    
    // ============================================================================
    // MÉTODOS PARA QUOTATION BUILDER (Filtrado de productos)
    // ============================================================================

    /**
     * Obtener productos para el constructor de cotizaciones con filtros
     */
    public function getProductsForQuotation(array $filters): LengthAwarePaginator
    {
        $warehouseId = $filters['warehouse_id'];
        $supplierId = $filters['supplier_id'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $search = $filters['search'] ?? null;
        $perPage = $filters['per_page'] ?? 20;

        $query = Product::query()
            ->where('is_active', true)
            ->with([
                'category.parent', // Familia
                'inventory' => fn($q) => $q->where('warehouse_id', $warehouseId),
            ]);

        // Filtrar por almacén (productos que existen en el inventario de ese almacén)
        $query->whereHas('inventory', function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        });

        // Filtrar por proveedor (productos que el proveedor ofrece)
        if ($supplierId) {
            $query->whereHas('supplierProducts', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)
                    ->where('is_active', true);
            })->with(['supplierProducts' => fn($q) => $q->where('supplier_id', $supplierId)]);
        }

        // Filtrar por categoría (cualquier nivel: categoría, familia o subfamilia)
        if ($categoryId) {
            // Obtener IDs de todas las subcategorías descendientes
            $category = Category::find($categoryId);
            if ($category) {
                $categoryIds = $category->getAllDescendantIds();
                $categoryIds[] = $categoryId;
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Búsqueda por texto
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('primary_name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Ordenar por stock disponible (primero los que tienen stock)
        $query->orderByRaw("
            (SELECT available_stock FROM inventory 
             WHERE inventory.product_id = products.id 
             AND inventory.warehouse_id = ?) DESC
        ", [$warehouseId]);

        return $query->paginate($perPage);
    }

    /**
     * Obtener proveedores que tienen productos asociados
     */
    public function getSuppliersWithProducts(?int $warehouseId = null): Collection
    {
        return Cache::remember(
            "suppliers_with_products_{$warehouseId}",
            now()->addMinutes(5),
            function () use ($warehouseId) {
                $query = Entity::suppliers()
                    ->active()
                    ->whereHas('supplierProducts', function ($q) {
                        $q->where('is_active', true);
                    })
                    ->withCount(['supplierProducts' => fn($q) => $q->where('is_active', true)]);

                // Si hay almacén, contar solo productos con inventario en ese almacén
                if ($warehouseId) {
                    $query->withCount(['supplierProducts as products_in_warehouse_count' => function ($q) use ($warehouseId) {
                        $q->where('is_active', true)
                            ->whereHas('product.inventory', fn($i) => $i->where('warehouse_id', $warehouseId));
                    }]);
                }

                return $query->get()->map(function ($supplier) {
                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->display_name,
                        'document' => $supplier->numero_documento,
                        'products_count' => $supplier->supplier_products_count,
                        'products_in_warehouse' => $supplier->products_in_warehouse_count ?? null,
                    ];
                });
            }
        );
    }

    /**
     * Obtener familias de categorías para filtros (nivel 1 - los iconos de la UI)
     */
    public function getCategoryFamilies(): Collection
    {
        return Cache::remember('category_families_for_quotation', now()->addHours(1), function () {
            // Obtener categorías de nivel 1 (familias) con conteo de productos
            return Category::family() // level = 1
                ->active()
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(function ($family) {
                    return [
                        'id' => $family->id,
                        'name' => $family->name,
                        'slug' => \Illuminate\Support\Str::slug($family->name),
                        'products_count' => $family->getTotalProductsRecursive(),
                        'icon' => $this->getFamilyIcon($family->name),
                    ];
                });
        });
    }

    /**
     * Obtener estadísticas globales para el builder
     */
    public function getBuilderStats(int $warehouseId): array
    {
        return Cache::remember(
            "quotation_builder_stats_{$warehouseId}",
            now()->addMinutes(5),
            function () use ($warehouseId) {
                $totalProducts = Product::active()->count();
                $productsWithStock = Inventory::where('warehouse_id', $warehouseId)
                    ->where('available_stock', '>', 0)
                    ->count();
                $totalSuppliers = Entity::suppliers()->active()
                    ->whereHas('supplierProducts', fn($q) => $q->where('is_active', true))
                    ->count();

                return [
                    'total_products' => $totalProducts,
                    'products_with_stock' => $productsWithStock,
                    'products_without_stock' => $totalProducts - $productsWithStock,
                    'total_suppliers' => $totalSuppliers,
                    'warehouse_id' => $warehouseId,
                ];
            }
        );
    }

    /**
     * Mapear nombre de familia a icono
     */
    private function getFamilyIcon(string $familyName): string
    {
        $icons = [
            'procesadores' => 'cpu',
            'memorias ram' => 'memory',
            'tarjetas de video' => 'gpu',
            'almacenamiento' => 'storage',
            'placas madre' => 'motherboard',
            'fuentes de poder' => 'power',
            'cases' => 'case',
            'coolers' => 'cooling',
            'monitores' => 'monitor',
            'teclados' => 'keyboard',
            'mouses' => 'mouse',
            'audifonos' => 'headphones',
            'parlantes' => 'speakers',
            'camara' => 'camera',
        ];

        $normalized = strtolower(trim($familyName));
        return $icons[$normalized] ?? 'box';
    }

    // ============================================================================
    // PDF GENERATION
    // ============================================================================

    /**
     * Genera y guarda el PDF de una cotización
     * 
     * @return string Path relativo del PDF guardado
     */
    public function generatePdf(Quotation $quotation): string
    {
        // Cargar relaciones necesarias
        $quotation->load(['details.supplier', 'details.product', 'user', 'customer', 'warehouse']);

        // Generar PDF
        $pdf = Pdf::loadView('pdf.quotation', compact('quotation'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        // Definir path de almacenamiento
        $year = $quotation->quotation_date->format('Y');
        $month = $quotation->quotation_date->format('m');
        $filename = "{$quotation->quotation_code}.pdf";
        $path = "quotations/{$year}/{$month}/{$filename}";

        // Guardar PDF
        Storage::disk('local')->put($path, $pdf->output());

        // Actualizar cotización con path del PDF
        $quotation->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * Obtiene el contenido del PDF para descarga directa
     */
    public function getPdfContent(Quotation $quotation): string
    {
        // Si ya existe el PDF, devolverlo
        if ($quotation->pdf_path && Storage::disk('local')->exists($quotation->pdf_path)) {
            return Storage::disk('local')->get($quotation->pdf_path);
        }

        // Si no existe, generarlo primero
        $this->generatePdf($quotation);
        return Storage::disk('local')->get($quotation->pdf_path);
    }

    // ============================================================================
    // BÚSQUEDA UNIFICADA DE PRODUCTOS (Inventario + Proveedores)
    // ============================================================================

    /**
     * Busca productos de forma unificada en inventario y proveedores
     * 
     * Retorna productos de ambas fuentes con un formato consistente
     */
    public function searchUnifiedProducts(array $filters): array
    {
        $search = $filters['search'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $supplierId = $filters['supplier_id'] ?? null;
        $warehouseId = $filters['warehouse_id'] ?? null;
        $sourceType = $filters['source_type'] ?? null; // 'inventory', 'supplier', null (ambos)
        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        $results = collect();

        // =========================================================
        // 1. Productos de INVENTARIO (tienen stock en almacén)
        // =========================================================
        if (!$sourceType || $sourceType === 'inventory') {
            $inventoryQuery = Inventory::with(['product.category', 'product.media', 'warehouse'])
                ->where('available_stock', '>', 0);

            if ($warehouseId) {
                $inventoryQuery->where('warehouse_id', $warehouseId);
            }

            if ($categoryId) {
                $category = Category::find($categoryId);
                if ($category) {
                    $categoryIds = $category->getAllDescendantIds();
                    $categoryIds[] = $categoryId;
                    $inventoryQuery->whereHas('product', fn($q) => $q->whereIn('category_id', $categoryIds));
                }
            }

            if ($search) {
                $inventoryQuery->whereHas('product', function ($q) use ($search) {
                    $q->where('sku', 'like', "%{$search}%")
                        ->orWhere('primary_name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            }

            $inventoryProducts = $inventoryQuery->get()->map(function ($inventory) {
                $product = $inventory->product;
                return [
                    'id' => "inv_{$inventory->id}",
                    'source_type' => 'inventory',
                    'inventory_id' => $inventory->id,
                    'product_id' => $product->id,
                    'product_name' => $product->primary_name,
                    'product_sku' => $product->sku,
                    'brand' => $product->brand,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'purchase_price' => (float) $inventory->cost_price,
                    'sale_price' => (float) $inventory->sale_price,
                    'available_stock' => $inventory->available_stock,
                    'warehouse_id' => $inventory->warehouse_id,
                    'warehouse_name' => $inventory->warehouse?->name,
                    'supplier_id' => null,
                    'supplier_name' => null,
                    'supplier_product_id' => null,
                    'delivery_days' => 0, // Stock inmediato
                    'image_url' => $product->getFirstMediaUrl('images') ?: null,
                ];
            });

            $results = $results->merge($inventoryProducts);
        }

        // =========================================================
        // 2. Productos de PROVEEDORES (supplier_products)
        // =========================================================
        if (!$sourceType || $sourceType === 'supplier') {
            $supplierQuery = SupplierProduct::with(['supplier', 'category', 'product'])
                ->active()
                ->available();

            if ($supplierId) {
                $supplierQuery->where('supplier_id', $supplierId);
            }

            if ($categoryId) {
                $category = Category::find($categoryId);
                if ($category) {
                    $categoryIds = $category->getAllDescendantIds();
                    $categoryIds[] = $categoryId;

                    // Buscar por category_id directo o por resolved category
                    $supplierQuery->where(function ($q) use ($categoryIds) {
                        $q->whereIn('category_id', $categoryIds)
                            ->orWhereHas('product', fn($pq) => $pq->whereIn('category_id', $categoryIds));
                    });
                }
            }

            if ($search) {
                $supplierQuery->where(function ($q) use ($search) {
                    $q->where('supplier_name', 'like', "%{$search}%")
                        ->orWhere('supplier_sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            }

            $supplierProducts = $supplierQuery->get()->map(function ($sp) {
                return [
                    'id' => "sup_{$sp->id}",
                    'source_type' => 'supplier',
                    'inventory_id' => null,
                    'product_id' => $sp->product_id,
                    'product_name' => $sp->supplier_name,
                    'product_sku' => $sp->supplier_sku,
                    'brand' => $sp->brand,
                    'category_id' => $sp->resolved_category_id ?? $sp->category_id,
                    'category_name' => $sp->category?->name,
                    'purchase_price' => (float) $sp->purchase_price,
                    'sale_price' => (float) $sp->sale_price,
                    'available_stock' => $sp->available_stock,
                    'warehouse_id' => null,
                    'warehouse_name' => null,
                    'supplier_id' => $sp->supplier_id,
                    'supplier_name' => $sp->supplier?->display_name ?? $sp->supplier?->business_name,
                    'supplier_product_id' => $sp->id,
                    'delivery_days' => $sp->delivery_days ?? 1,
                    'image_url' => $sp->image_url,
                ];
            });

            $results = $results->merge($supplierProducts);
        }

        // =========================================================
        // 3. Ordenar y paginar
        // =========================================================
        $sorted = $results->sortByDesc('available_stock')->values();
        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $sorted->slice($offset, $perPage)->values();

        return [
            'data' => $paginated,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }
}

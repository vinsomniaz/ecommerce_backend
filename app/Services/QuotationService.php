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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        private SettingService $settingService,
        private MarginCalculatorService $marginCalculator,
        private CommissionCalculatorService $commissionCalculator
    ) {}
    
    public function create(array $data, User $user): Quotation
    {
        return DB::transaction(function() use ($data, $user) {
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
                        'slug' => \Str::slug($family->name),
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
}
<?php
// app/Services/InventoryService.php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Exceptions\Inventory\InventoryAlreadyExistsException;
use App\Exceptions\Inventory\InventoryNotFoundException;
use App\Exceptions\Inventory\InventoryHasStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryService
{
    /**
     * Obtener inventario filtrado
     */
    public function getFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Inventory::query()
            ->with(['product.category', 'product.media', 'warehouse']);

        // Filtro por producto específico
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Filtro por almacén específico
        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Búsqueda en productos
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('primary_name', 'like', "%{$search}%")
                    ->orWhere('secondary_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Solo con stock disponible
        if (!empty($filters['with_stock'])) {
            $query->where('available_stock', '>', 0);
        }

        // Stock bajo (menor o igual al mínimo del producto)
        if (!empty($filters['low_stock'])) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('inventory.available_stock', '<=', 'products.min_stock');
            });
        }

        // Sin stock
        if (!empty($filters['out_of_stock'])) {
            $query->where('available_stock', 0);
        }

        // Rango de precios
        if (!empty($filters['min_price'])) {
            $query->where('sale_price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('sale_price', '<=', $filters['max_price']);
        }

        // Ordenamiento
        $sortBy = $filters['sort_by'] ?? 'last_movement_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = ['available_stock', 'reserved_stock', 'sale_price', 'last_movement_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($perPage);
    }

    /**
     * Obtener inventario de un producto en todos los almacenes
     */
    public function getByProduct(int $productId): Collection
    {
        return Inventory::where('product_id', $productId)
            ->with('warehouse')
            ->get();
    }

    /**
     * Obtener inventario de un almacén con filtros
     */
    public function getByWarehouse(int $warehouseId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Inventory::where('warehouse_id', $warehouseId)
            ->with(['product.category', 'product.media']);

        // Búsqueda
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('primary_name', 'like', "%{$search}%")
                    ->orWhere('secondary_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filtro por categoría
        if (!empty($filters['category_id'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        // Solo con stock
        if (!empty($filters['with_stock'])) {
            $query->where('available_stock', '>', 0);
        }

        // Stock bajo
        if (!empty($filters['low_stock'])) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('inventory.available_stock', '<=', 'products.min_stock');
            });
        }

        return $query->orderBy('last_movement_at', 'desc')->paginate($perPage);
    }

    /**
     * Obtener inventario específico (producto + almacén)
     */
    public function getSpecific(int $productId, int $warehouseId): Inventory
    {
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->with(['product', 'warehouse'])
            ->first();

        if (!$inventory) {
            throw new InventoryNotFoundException(
                "No existe inventario para el producto ID {$productId} en el almacén ID {$warehouseId}"
            );
        }

        return $inventory;
    }

    /**
     * Asignar producto a uno o múltiples almacenes
     */
    public function assignToWarehouses(int $productId, array $warehouseIds, array $priceData = []): array
    {
        return DB::transaction(function () use ($productId, $warehouseIds, $priceData) {
            $product = Product::findOrFail($productId);
            $assigned = 0;
            $skipped = 0;
            $results = [];

            foreach ($warehouseIds as $warehouseId) {
                $warehouse = Warehouse::findOrFail($warehouseId);

                // Verificar si ya existe
                $exists = Inventory::where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $results[] = [
                        'warehouse_id' => $warehouseId,
                        'warehouse_name' => $warehouse->name,
                        'status' => 'skipped',
                        'message' => 'Ya existe en este almacén',
                    ];
                    continue;
                }

                // Crear inventario
                Inventory::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'available_stock' => 0,
                    'reserved_stock' => 0,
                    'sale_price' => $priceData['sale_price'] ?? null,
                    'profit_margin' => $priceData['profit_margin'] ?? null,
                    'min_sale_price' => $priceData['min_sale_price'] ?? null,
                    'price_updated_at' => isset($priceData['sale_price']) ? now() : null,
                    'last_movement_at' => now(),
                ]);

                $assigned++;
                $results[] = [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouse->name,
                    'status' => 'assigned',
                    'message' => 'Asignado exitosamente',
                ];

                // Log
                activity()
                    ->performedOn($product)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'warehouse_id' => $warehouseId,
                        'warehouse_name' => $warehouse->name,
                        'price_data' => $priceData,
                    ])
                    ->log("Producto asignado al almacén: {$warehouse->name}");
            }

            return [
                'assigned' => $assigned,
                'skipped' => $skipped,
                'total' => count($warehouseIds),
                'details' => $results,
            ];
        });
    }

    /**
     * Asignación masiva: múltiples productos a múltiples almacenes
     */
    public function bulkAssign(array $productIds, array $warehouseIds, array $priceData = []): array
    {
        return DB::transaction(function () use ($productIds, $warehouseIds, $priceData) {
            $totalAssigned = 0;
            $totalSkipped = 0;
            $results = [];

            foreach ($productIds as $productId) {
                $product = Product::find($productId);
                if (!$product) {
                    continue;
                }

                $productResult = $this->assignToWarehouses($productId, $warehouseIds, $priceData);
                $totalAssigned += $productResult['assigned'];
                $totalSkipped += $productResult['skipped'];

                $results[] = [
                    'product_id' => $productId,
                    'product_name' => $product->primary_name,
                    'assigned' => $productResult['assigned'],
                    'skipped' => $productResult['skipped'],
                ];
            }

            return [
                'total_assigned' => $totalAssigned,
                'total_skipped' => $totalSkipped,
                'products_processed' => count($productIds),
                'warehouses_targeted' => count($warehouseIds),
                'details' => $results,
            ];
        });
    }

    /**
     * Actualizar inventario (precios, configuración)
     */
    public function update(int $productId, int $warehouseId, array $data): Inventory
    {
        return DB::transaction(function () use ($productId, $warehouseId, $data) {
            $inventory = $this->getSpecific($productId, $warehouseId);
            $oldData = $inventory->toArray();

            // Actualizar precio si cambió
            if (isset($data['sale_price']) && $data['sale_price'] !== $inventory->sale_price) {
                $data['price_updated_at'] = now();
            }

            $inventory->update($data);

            // Log
            activity()
                ->performedOn($inventory->product)
                ->causedBy(auth()->user())
                ->withProperties([
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $inventory->warehouse->name,
                    'old' => $oldData,
                    'new' => $inventory->fresh()->toArray(),
                ])
                ->log('Inventario actualizado');

            return $inventory->fresh(['product', 'warehouse']);
        });
    }

    /**
     * Eliminar inventario (desasignar)
     */
    public function delete(int $productId, int $warehouseId): bool
    {
        return DB::transaction(function () use ($productId, $warehouseId) {
            $inventory = $this->getSpecific($productId, $warehouseId);

            // Verificar que no haya stock
            if ($inventory->available_stock > 0 || $inventory->reserved_stock > 0) {
                throw new InventoryHasStockException(
                    "No se puede eliminar el inventario mientras tenga stock. " .
                    "Stock disponible: {$inventory->available_stock}, " .
                    "Stock reservado: {$inventory->reserved_stock}"
                );
            }

            $warehouseName = $inventory->warehouse->name;
            $inventory->delete();

            // Log
            activity()
                ->performedOn($inventory->product)
                ->causedBy(auth()->user())
                ->withProperties([
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouseName,
                ])
                ->log("Producto desasignado del almacén: {$warehouseName}");

            return true;
        });
    }

    /**
     * Estadísticas de un producto en todos sus almacenes
     */
    public function getProductStatistics(int $productId): array
    {
        $inventories = Inventory::where('product_id', $productId)
            ->with('warehouse')
            ->get();

        return [
            'total_warehouses' => $inventories->count(),
            'active_warehouses' => $inventories->filter(fn($i) => $i->warehouse->is_active)->count(),
            'total_available_stock' => $inventories->sum('available_stock'),
            'total_reserved_stock' => $inventories->sum('reserved_stock'),
            'total_stock' => $inventories->sum(fn($i) => $i->available_stock + $i->reserved_stock),
            'warehouses_with_stock' => $inventories->where('available_stock', '>', 0)->count(),
            'warehouses_out_of_stock' => $inventories->where('available_stock', 0)->count(),
            'average_sale_price' => round($inventories->whereNotNull('sale_price')->avg('sale_price') ?? 0, 2),
            'highest_price' => $inventories->whereNotNull('sale_price')->max('sale_price') ?? 0,
            'lowest_price' => $inventories->whereNotNull('sale_price')->min('sale_price') ?? 0,
        ];
    }

    /**
     * Estadísticas de un almacén
     */
    public function getWarehouseStatistics(int $warehouseId): array
    {
        $inventories = Inventory::where('warehouse_id', $warehouseId)
            ->with('product')
            ->get();

        $lowStock = $inventories->filter(function ($inventory) {
            return $inventory->available_stock <= $inventory->product->min_stock;
        });

        return [
            'total_products' => $inventories->count(),
            'products_with_stock' => $inventories->where('available_stock', '>', 0)->count(),
            'products_out_of_stock' => $inventories->where('available_stock', 0)->count(),
            'products_low_stock' => $lowStock->count(),
            'total_available_stock' => $inventories->sum('available_stock'),
            'total_reserved_stock' => $inventories->sum('reserved_stock'),
            'total_inventory_value' => $this->calculateInventoryValue($warehouseId),
        ];
    }

    /**
     * Estadísticas globales de inventario
     */
    public function getGlobalStatistics(): array
    {
        $totalInventory = Inventory::count();
        $withStock = Inventory::where('available_stock', '>', 0)->count();
        $outOfStock = Inventory::where('available_stock', 0)->count();

        $lowStock = Inventory::whereHas('product', function ($q) {
            $q->whereColumn('inventory.available_stock', '<=', 'products.min_stock');
        })->count();

        return [
            'total_inventory_records' => $totalInventory,
            'records_with_stock' => $withStock,
            'records_out_of_stock' => $outOfStock,
            'records_low_stock' => $lowStock,
            'total_available_stock' => Inventory::sum('available_stock'),
            'total_reserved_stock' => Inventory::sum('reserved_stock'),
            'unique_products' => Inventory::distinct('product_id')->count('product_id'),
            'unique_warehouses' => Inventory::distinct('warehouse_id')->count('warehouse_id'),
            'total_inventory_value' => $this->calculateTotalInventoryValue(),
        ];
    }

    /**
     * Obtener productos con stock bajo
     */
    public function getLowStockItems(int $perPage = 15): LengthAwarePaginator
    {
        return Inventory::whereHas('product', function ($q) {
            $q->whereColumn('inventory.available_stock', '<=', 'products.min_stock');
        })
            ->with(['product.category', 'warehouse'])
            ->orderBy('available_stock', 'asc')
            ->paginate($perPage);
    }

    /**
     * Obtener productos sin stock
     */
    public function getOutOfStockItems(int $perPage = 15): LengthAwarePaginator
    {
        return Inventory::where('available_stock', 0)
            ->with(['product.category', 'warehouse'])
            ->orderBy('last_movement_at', 'desc')
            ->paginate($perPage);
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Calcular valor total del inventario en un almacén
     */
    private function calculateInventoryValue(int $warehouseId): float
    {
        $value = DB::table('inventory')
            ->join('purchase_batches', function ($join) {
                $join->on('inventory.product_id', '=', 'purchase_batches.product_id')
                    ->where('purchase_batches.status', 'active');
            })
            ->where('inventory.warehouse_id', $warehouseId)
            ->selectRaw('SUM(inventory.available_stock * purchase_batches.distribution_price) as total')
            ->value('total');

        return round($value ?? 0, 2);
    }

    /**
     * Calcular valor total de todo el inventario
     */
    private function calculateTotalInventoryValue(): float
    {
        $value = DB::table('inventory')
            ->join('purchase_batches', function ($join) {
                $join->on('inventory.product_id', '=', 'purchase_batches.product_id')
                    ->where('purchase_batches.status', 'active');
            })
            ->selectRaw('SUM(inventory.available_stock * purchase_batches.distribution_price) as total')
            ->value('total');

        return round($value ?? 0, 2);
    }
}

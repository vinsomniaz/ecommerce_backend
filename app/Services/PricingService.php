<?php

namespace App\Services;

use App\Models\{Category, Inventory, Product, Supports\PurchaseBatch};
use Illuminate\Support\Facades\{DB, Log, Cache};

/**
 * Servicio para gestión automática de precios y costos
 * ✅ Implementa herencia de márgenes desde categorías padre
 */
class PricingService
{
    /**
     * Recalcula precios de una categoría cuando cambian sus márgenes
     * ✅ Usa márgenes efectivos (con herencia)
     */
    // public function recalculateCategoryPricing(Category $category, ?float $oldMarginRetail = null): array
    // {
    //     return DB::transaction(function () use ($category, $oldMarginRetail) {
    //         // Obtener IDs de categorías (incluye hijos y nietos)
    //         $categoryIds = $category->getAllDescendantIdsWithCache();

    //         // Obtener productos de esas categorías
    //         $productIds = Product::whereIn('category_id', $categoryIds)->pluck('id');

    //         if ($productIds->isEmpty()) {
    //             Log::info('No hay productos en esta categoría para recalcular', [
    //                 'category_id' => $category->id,
    //                 'category_name' => $category->name,
    //             ]);

    //             return [
    //                 'success' => true,
    //                 'inventories_updated' => 0,
    //                 'message' => 'No hay productos en esta categoría',
    //             ];
    //         }

    //         // Obtener inventarios con costo promedio válido
    //         $inventories = Inventory::whereIn('product_id', $productIds)
    //             ->where('average_cost', '>', 0)
    //             ->with('product.category') // ✅ Cargar categoría para obtener márgenes
    //             ->get();

    //         if ($inventories->isEmpty()) {
    //             Log::warning('No hay inventarios con costo promedio válido para actualizar', [
    //                 'category_id' => $category->id,
    //                 'products_count' => $productIds->count(),
    //             ]);

    //             return [
    //                 'success' => true,
    //                 'inventories_updated' => 0,
    //                 'message' => 'No hay inventarios con costo promedio válido',
    //             ];
    //         }

    //         $updated = 0;
    //         $errors = [];

    //         foreach ($inventories as $inventory) {
    //             try {
    //                 // ✅ Obtener la categoría del producto (puede ser hija de la categoría actualizada)
    //                 $productCategory = $inventory->product->category;

    //                 if (!$productCategory) {
    //                     Log::warning('Producto sin categoría', [
    //                         'product_id' => $inventory->product_id,
    //                     ]);
    //                     continue;
    //                 }

    //                 // ✅ USAR MÁRGENES EFECTIVOS (con herencia)
    //                 $effectiveNormalMargin = $productCategory->getEffectiveNormalMargin();
    //                 $effectiveMinMargin = $productCategory->getEffectiveMinMargin();

    //                 // Calcular nuevos precios
    //                 $newSalePrice = $this->calculateSalePrice(
    //                     $inventory->average_cost,
    //                     $effectiveNormalMargin
    //                 );

    //                 $newMinSalePrice = $this->calculateSalePrice(
    //                     $inventory->average_cost,
    //                     $effectiveMinMargin
    //                 );

    //                 // Actualizar inventario
    //                 $inventory->update([
    //                     'sale_price' => $newSalePrice,
    //                     'profit_margin' => $effectiveNormalMargin,
    //                     'min_sale_price' => $newMinSalePrice,
    //                     'price_updated_at' => now(),
    //                 ]);

    //                 $updated++;

    //                 Log::debug('Precio actualizado con margen efectivo', [
    //                     'inventory_id' => $inventory->id,
    //                     'product_id' => $inventory->product_id,
    //                     'product_category' => $productCategory->name,
    //                     'category_own_margin' => $productCategory->normal_margin_percentage,
    //                     'effective_margin' => $effectiveNormalMargin,
    //                     'inherited' => $productCategory->inheritsMargins(),
    //                     'average_cost' => $inventory->average_cost,
    //                     'new_sale_price' => $newSalePrice,
    //                 ]);

    //             } catch (\Exception $e) {
    //                 $errors[] = [
    //                     'inventory_id' => $inventory->id,
    //                     'product_id' => $inventory->product_id,
    //                     'warehouse_id' => $inventory->warehouse_id,
    //                     'error' => $e->getMessage(),
    //                 ];

    //                 Log::error('Error actualizando precio de inventario', [
    //                     'inventory_id' => $inventory->id,
    //                     'product_id' => $inventory->product_id,
    //                     'warehouse_id' => $inventory->warehouse_id,
    //                     'error' => $e->getMessage(),
    //                     'trace' => $e->getTraceAsString(),
    //                 ]);
    //             }
    //         }

    //         // 🔥 SI HAY ERRORES, LANZAR EXCEPCIÓN
    //         if (!empty($errors)) {
    //             $errorMessage = "Se encontraron " . count($errors) . " errores al actualizar precios";

    //             Log::error('Errores críticos en recálculo de precios', [
    //                 'total_errors' => count($errors),
    //                 'errors' => $errors,
    //             ]);

    //             throw new \Exception($errorMessage . ". Detalles: " . json_encode($errors));
    //         }

    //         Log::info('Precios recalculados exitosamente por cambio de margen', [
    //             'category_id' => $category->id,
    //             'category_name' => $category->name,
    //             'old_margin' => $oldMarginRetail,
    //             'new_margin' => $category->normal_margin_percentage,
    //             'effective_margin' => $category->getEffectiveNormalMargin(),
    //             'products_affected' => $productIds->count(),
    //             'inventories_updated' => $updated,
    //         ]);

    //         return [
    //             'success' => true,
    //             'category_id' => $category->id,
    //             'category_name' => $category->name,
    //             'products_affected' => $productIds->count(),
    //             'inventories_updated' => $updated,
    //             'inventories_skipped' => 0,
    //         ];
    //     });
    // }

    /**
     * Recalcula el costo promedio ponderado GLOBAL y actualiza precios
     * ✅ Usa márgenes efectivos de la categoría del producto
     * ✅ El costo es GLOBAL (todos los almacenes), el precio es el MISMO para todos
     */
    public function recalculateInventoryCost(int $productId, int $warehouseId): array
    {
        return DB::transaction(function () use ($productId, $warehouseId) {
            $inventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if (!$inventory) {
                return [
                    'success' => false,
                    'message' => 'Inventario no encontrado',
                ];
            }

            $oldAverageCost = $inventory->average_cost;

            // ✅ Calcular costo promedio GLOBAL
            $averageCost = $this->calculateGlobalWeightedAverageCost($productId);
            $inventory->average_cost = $averageCost;
            $inventory->save(); // ✅ SOLO guarda average_cost, NO toca sale_price

            Log::info('Costo promedio actualizado (precios NO modificados)', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'old_average_cost' => $oldAverageCost,
                'new_average_cost' => $averageCost,
                'sale_price' => $inventory->sale_price, // Se mantiene igual
                'note' => 'Los precios ahora se calculan dinámicamente',
            ]);

            return [
                'success' => true,
                'average_cost_updated' => true,
                'prices_updated' => now(),
                'old_average_cost' => $oldAverageCost,
                'new_average_cost' => $averageCost,
                'message' => 'Costo actualizado, precios se calculan dinámicamente',
            ];
        });
    }

    /**
     * ✅ Calcula el costo promedio ponderado GLOBAL de todos los lotes activos
     * (de TODOS los almacenes, no solo uno)
     *
     * Fórmula: Σ(cantidad × purchase_price) / Σ(cantidad)
     *
     * ⚠️ IMPORTANTE: Solo cuenta lotes con status='active' y quantity_available > 0
     * Los lotes agotados (depleted) NO se incluyen en el cálculo
     */
    public function calculateGlobalWeightedAverageCost(int $productId): float
    {
        $batches = PurchaseBatch::where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->select('warehouse_id', 'quantity_available', 'purchase_price', 'batch_code')
            ->get();

        if ($batches->isEmpty()) {
            Log::debug('No hay lotes activos para calcular costo promedio', [
                'product_id' => $productId,
            ]);
            return 0;
        }

        $totalCost = $batches->sum(fn($b) => $b->quantity_available * $b->purchase_price);
        $totalQuantity = $batches->sum('quantity_available');

        if ($totalQuantity <= 0) {
            return 0;
        }

        $averageCost = $totalCost / $totalQuantity;

        Log::debug('Costo promedio GLOBAL calculado', [
            'product_id' => $productId,
            'total_batches' => $batches->count(),
            'total_quantity' => $totalQuantity,
            'total_cost' => $totalCost,
            'average_cost' => $averageCost,
            'warehouses' => $batches->pluck('warehouse_id')->unique()->values()->toArray(),
        ]);

        return round($averageCost, 4);
    }

    /**
     * @deprecated Usar calculateGlobalWeightedAverageCost() en su lugar
     */
    public function calculateWeightedAverageCost(int $productId, int $warehouseId): float
    {
        // Redirigir al método global
        return $this->calculateGlobalWeightedAverageCost($productId);
    }

    /**
     * Calcula precio de venta aplicando margen al costo
     *
     * ✅ FÓRMULA CORRECTA: Precio = Costo × (1 + Margen/100)
     *
     * Ejemplo:
     * - Costo: S/ 100
     * - Margen: 30%
     * - Precio = 100 × (1 + 30/100) = 100 × 1.30 = 130
     * - Ganancia = 130 - 100 = 30 (que es el 30% de 100) ✅
     *
     * ✅ El precio se redondea a ENTERO (sin decimales)
     */
    public function calculateSalePrice(float $cost, float $marginPercent): float
    {
        if ($cost <= 0) {
            return 0;
        }

        // Validar margen razonable
        if ($marginPercent < 0) {
            Log::warning('Margen negativo detectado, usando 0%', [
                'cost' => $cost,
                'margin' => $marginPercent,
            ]);
            $marginPercent = 0;
        }

        if ($marginPercent > 200) {
            Log::warning('Margen muy alto detectado', [
                'cost' => $cost,
                'margin' => $marginPercent,
            ]);
        }

        // ✅ FÓRMULA CORRECTA
        $salePrice = $cost * (1 + ($marginPercent / 100));

        // ✅ REDONDEAR A ENTERO (sin decimales)
        return round($salePrice, 0);
    }

    /**
     * ✅ NUEVO: Calcula precios sugeridos sin guardar
     */
    public function getSuggestedPrices(int $productId, int $warehouseId): array
    {
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->with('product.category')
            ->first();

        if (!$inventory || !$inventory->product) {
            return [
                'suggested_sale_price' => null,
                'suggested_min_sale_price' => null,
                'margin_used' => null,
                'min_margin_used' => null,
            ];
        }

        $category = $inventory->product->category;

        if (!$category) {
            return [
                'suggested_sale_price' => null,
                'suggested_min_sale_price' => null,
                'margin_used' => null,
                'min_margin_used' => null,
            ];
        }

        $effectiveNormalMargin = $category->getEffectiveNormalMargin();
        $effectiveMinMargin = $category->getEffectiveMinMargin();

        $suggestedSalePrice = $this->calculateSalePrice(
            $inventory->average_cost,
            $effectiveNormalMargin
        );

        $suggestedMinSalePrice = $this->calculateSalePrice(
            $inventory->average_cost,
            $effectiveMinMargin
        );

        return [
            'suggested_sale_price' => $suggestedSalePrice,
            'suggested_min_sale_price' => $suggestedMinSalePrice,
            'margin_used' => $effectiveNormalMargin,
            'min_margin_used' => $effectiveMinMargin,
            'average_cost' => $inventory->average_cost,
            'category_name' => $category->name,
            'inherits_margin' => $category->inheritsMargins(),
        ];
    }

    /**
     * Calcula el margen real de un inventario
     * Útil para reportes y validaciones
     *
     * ✅ FÓRMULA: ((Precio - Costo) / Costo) × 100
     *
     * Ejemplo:
     * - Costo: S/ 100
     * - Precio: S/ 130
     * - Margen = ((130 - 100) / 100) × 100 = 30% ✅
     */
    public function calculateActualMargin(float $cost, float $salePrice): float
    {
        if ($cost <= 0 || $salePrice <= 0 || $salePrice <= $cost) {
            return 0;
        }

        // Margen = ((Precio - Costo) / Costo) × 100
        return round((($salePrice - $cost) / $cost) * 100, 2);
    }

    /**
     * ✅ NUEVO: Calcula el costo a partir del precio y margen
     * Útil para validaciones inversas
     *
     * FÓRMULA: Costo = Precio / (1 + Margen/100)
     */
    public function calculateCostFromPrice(float $salePrice, float $marginPercent): float
    {
        if ($salePrice <= 0) {
            return 0;
        }

        if ($marginPercent < 0) {
            return $salePrice; // Si no hay margen, el costo es el precio
        }

        return round($salePrice / (1 + ($marginPercent / 100)), 4);
    }

    /**
     * ✅ NUEVO: Verifica si un precio calculado es correcto
     * Útil para debugging y validaciones
     */
    public function verifyPricing(float $cost, float $salePrice, float $marginPercent): array
    {
        $expectedPrice = $this->calculateSalePrice($cost, $marginPercent);
        $actualMargin = $this->calculateActualMargin($cost, $salePrice);
        $priceDifference = abs($salePrice - $expectedPrice);
        $marginDifference = abs($actualMargin - $marginPercent);

        return [
            'cost' => round($cost, 2),
            'sale_price' => round($salePrice, 0),
            'expected_price' => $expectedPrice,
            'margin_percent' => $marginPercent,
            'actual_margin' => $actualMargin,
            'price_difference' => $priceDifference,
            'margin_difference' => round($marginDifference, 2),
            'is_correct' => $priceDifference <= 1, // Tolerar 1 sol por redondeo
            'needs_update' => $priceDifference > 1,
        ];
    }

    /**
     * Recalcula costos y precios para todos los inventarios de un producto
     * Útil cuando se hacen cambios masivos
     */
    public function recalculateProductAllWarehouses(int $productId, ?float $newSalePrice = null): array
    {
        $inventories = Inventory::where('product_id', $productId)->get();

        $results = [];
        $updated = 0;
        $skipped = 0;
        $sale_price = 0;

        foreach ($inventories as $inventory) {

            //Setea el nuevo precio de venta
            if (isset($newSalePrice)) {
                $inventory->update([
                    'sale_price' => $newSalePrice
                ]);
                $sale_price = $newSalePrice;
            }

            //Recalcula costos y termina diciendo que se actualizaron los precios
            $result = $this->recalculateInventoryCost(
                $inventory->product_id,
                $inventory->warehouse_id
            );

            if ($result['success'] && $result['prices_updated']) {
                $updated++;
            } else {
                $skipped++;
            }

            $results[] = $result;
        }

        return [
            'success' => true,
            'product_id' => $productId,
            'total_warehouses' => $inventories->count(),
            'updated' => $updated,
            'skipped' => $skipped,
            'details' => $results,
            'sale_price' => $sale_price ?: null,
        ];
    }
}

<?php
// app/Services/ProductPriceService.php

namespace App\Services;

use App\Models\ProductPrice;
use App\Models\Product;
use App\Models\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductPriceService
{
    /**
     * Obtener precios con filtros
     */
    public function getFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductPrice::query()
            ->with(['product', 'priceList', 'warehouse']);

        // Filtro por producto
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        // Filtro por lista de precios
        if (!empty($filters['price_list_id'])) {
            $query->where('price_list_id', $filters['price_list_id']);
        }

        // Filtro por almacén
        if (isset($filters['warehouse_id'])) {
            if ($filters['warehouse_id'] === 'null' || $filters['warehouse_id'] === null) {
                $query->whereNull('warehouse_id');
            } else {
                $query->where('warehouse_id', $filters['warehouse_id']);
            }
        }

        // Filtro por estado
        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Ordenamiento
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Crear un nuevo precio
     */
    public function create(array $data): ProductPrice
    {
        return DB::transaction(function () use ($data) {
            $price = ProductPrice::create($data);

            activity()
                ->performedOn($price)
                ->causedBy(Auth::user())
                ->withProperties($data)
                ->log('Precio de producto creado');

            return $price->fresh(['product', 'priceList', 'warehouse']);
        });
    }

    /**
     * Actualizar un precio existente
     */
    public function update(ProductPrice $price, array $data): ProductPrice
    {
        return DB::transaction(function () use ($price, $data) {
            $oldData = $price->toArray();

            $price->update($data);

            activity()
                ->performedOn($price)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old' => $oldData,
                    'new' => $price->fresh()->toArray(),
                ])
                ->log('Precio de producto actualizado');

            return $price->fresh(['product', 'priceList', 'warehouse']);
        });
    }

    /**
     * Eliminar un precio
     */
    public function delete(ProductPrice $price): bool
    {
        activity()
            ->performedOn($price)
            ->causedBy(Auth::user())
            ->withProperties($price->toArray())
            ->log('Precio de producto eliminado');

        return $price->delete();
    }

    /**
     * Actualización masiva de precios
     *
     * @param array $productIds IDs de productos
     * @param int $priceListId ID de lista de precios
     * @param int|null $warehouseId ID de almacén (null = general)
     * @param string $adjustmentType 'percentage', 'fixed', 'replace'
     * @param float $adjustmentValue Valor del ajuste
     * @param bool $applyToMinPrice Si aplicar también a min_price
     */
    public function bulkUpdate(
        array $productIds,
        int $priceListId,
        ?int $warehouseId,
        string $adjustmentType,
        float $adjustmentValue,
        bool $applyToMinPrice = false
    ): int {
        return DB::transaction(function () use (
            $productIds,
            $priceListId,
            $warehouseId,
            $adjustmentType,
            $adjustmentValue,
            $applyToMinPrice
        ) {
            $query = ProductPrice::whereIn('product_id', $productIds)
                ->where('price_list_id', $priceListId)
                ->where('is_active', true);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            } else {
                $query->whereNull('warehouse_id');
            }

            $prices = $query->get();
            $count = 0;

            foreach ($prices as $price) {
                $newPrice = $this->calculateAdjustedPrice($price->price, $adjustmentType, $adjustmentValue);

                $updateData = ['price' => round($newPrice, 2)];

                if ($applyToMinPrice && $price->min_price) {
                    $newMinPrice = $this->calculateAdjustedPrice($price->min_price, $adjustmentType, $adjustmentValue);
                    $updateData['min_price'] = round($newMinPrice, 2);
                }

                $price->update($updateData);
                $count++;
            }

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'product_count' => $count,
                    'price_list_id' => $priceListId,
                    'warehouse_id' => $warehouseId,
                    'adjustment_type' => $adjustmentType,
                    'adjustment_value' => $adjustmentValue,
                ])
                ->log('Actualización masiva de precios');

            return $count;
        });
    }

    /**
     * Copiar precios de una lista a otra
     */
    public function copyPrices(
        int $sourcePriceListId,
        int $targetPriceListId,
        ?array $productIds = null,
        ?float $adjustmentPercentage = null
    ): int {
        return DB::transaction(function () use (
            $sourcePriceListId,
            $targetPriceListId,
            $productIds,
            $adjustmentPercentage
        ) {
            $query = ProductPrice::where('price_list_id', $sourcePriceListId)
                ->where('is_active', true);

            if ($productIds) {
                $query->whereIn('product_id', $productIds);
            }

            $sourcePrices = $query->get();
            $count = 0;

            foreach ($sourcePrices as $sourcePrice) {
                // Calcular nuevo precio con ajuste si existe
                $newPrice = $sourcePrice->price;
                $newMinPrice = $sourcePrice->min_price;

                if ($adjustmentPercentage !== null) {
                    $multiplier = 1 + ($adjustmentPercentage / 100);
                    $newPrice = $sourcePrice->price * $multiplier;
                    if ($newMinPrice) {
                        $newMinPrice = $sourcePrice->min_price * $multiplier;
                    }
                }

                // Verificar si ya existe
                $exists = ProductPrice::where('product_id', $sourcePrice->product_id)
                    ->where('price_list_id', $targetPriceListId)
                    ->where('warehouse_id', $sourcePrice->warehouse_id)
                    ->where('min_quantity', $sourcePrice->min_quantity)
                    ->exists();

                if (!$exists) {
                    ProductPrice::create([
                        'product_id' => $sourcePrice->product_id,
                        'price_list_id' => $targetPriceListId,
                        'warehouse_id' => $sourcePrice->warehouse_id,
                        'price' => round($newPrice, 2),
                        'min_price' => $newMinPrice ? round($newMinPrice, 2) : null,
                        'currency' => $sourcePrice->currency,
                        'min_quantity' => $sourcePrice->min_quantity,
                        'is_active' => true,
                    ]);

                    $count++;
                }
            }

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'source_price_list_id' => $sourcePriceListId,
                    'target_price_list_id' => $targetPriceListId,
                    'prices_copied' => $count,
                    'adjustment_percentage' => $adjustmentPercentage,
                ])
                ->log('Precios copiados entre listas');

            return $count;
        });
    }

    /**
     * Calcular precio sugerido basado en costo y margen de categoría
     */
    public function calculateSuggestedPrice(int $productId, float $marginPercentage, ?float $baseCost = null): array
    {
        $product = Product::with('category')->findOrFail($productId);

        // Usar costo base proporcionado o el promedio del producto
        $cost = $baseCost ?? $product->average_cost;

        if ($cost <= 0) {
            throw new \Exception('No se puede calcular el precio sin un costo base válido');
        }

        $suggestedPrice = $cost * (1 + $marginPercentage / 100);

        // Obtener margen mínimo de la categoría si existe
        $minMargin = $product->category?->min_margin_percentage ?? 0;
        $minSuggestedPrice = $cost * (1 + $minMargin / 100);

        return [
            'product_id' => $productId,
            'base_cost' => round($cost, 2),
            'margin_percentage' => $marginPercentage,
            'suggested_price' => round($suggestedPrice, 2),
            'min_margin_percentage' => $minMargin,
            'min_suggested_price' => round($minSuggestedPrice, 2),
            'profit_amount' => round($suggestedPrice - $cost, 2),
        ];
    }

    /**
     * Obtener estadísticas de precios
     */
    public function getStatistics(): array
    {
        $totalPrices = ProductPrice::count();
        $activePrices = ProductPrice::where('is_active', true)->count();

        $pricesWithMinPrice = ProductPrice::whereNotNull('min_price')
            ->where('is_active', true)
            ->count();

        $warehouseSpecificPrices = ProductPrice::whereNotNull('warehouse_id')
            ->where('is_active', true)
            ->count();

        $generalPrices = ProductPrice::whereNull('warehouse_id')
            ->where('is_active', true)
            ->count();

        return [
            'total_prices' => $totalPrices,
            'active_prices' => $activePrices,
            'prices_with_min_price' => $pricesWithMinPrice,
            'warehouse_specific_prices' => $warehouseSpecificPrices,
            'general_prices' => $generalPrices,
            'products_with_prices' => ProductPrice::distinct('product_id')->count('product_id'),
        ];
    }

    /**
     * Desactivar precios expirados
     */
    public function deactivateExpiredPrices(): int
    {
        return DB::transaction(function () {
            $count = ProductPrice::where('is_active', true)
                ->whereNotNull('valid_to')
                ->where('valid_to', '<', now())
                ->update(['is_active' => false]);

            if ($count > 0) {
                activity()
                    ->causedBy(Auth::user())
                    ->withProperties(['deactivated_count' => $count])
                    ->log('Precios expirados desactivados automáticamente');
            }

            return $count;
        });
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Calcular precio ajustado según tipo
     */
    private function calculateAdjustedPrice(float $currentPrice, string $type, float $value): float
    {
        return match ($type) {
            'percentage' => $currentPrice * (1 + $value / 100),
            'fixed' => $currentPrice + $value,
            'replace' => $value,
            default => $currentPrice,
        };
    }
}

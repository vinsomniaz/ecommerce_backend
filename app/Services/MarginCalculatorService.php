<?php

namespace App\Services;

use App\Models\QuotationDetail;
use App\Models\Product;
use App\Exceptions\LowMarginException;
use Illuminate\Support\Facades\DB;

class MarginCalculatorService
{
    public function __construct(
        private SettingService $settingService
    ) {}

    /**
     * Calcula márgenes de un item de cotización
     */
    public function calculate(QuotationDetail $detail): array
    {
        // 1. Determinar costo unitario real según origen
        $unitCost = $this->determineUnitCost($detail);

        // 2. Calcular totales de costo
        $totalCost = $unitCost * $detail->quantity;

        // 3. Calcular subtotal del item (precio x cantidad - descuento)
        $itemSubtotal = ($detail->unit_price * $detail->quantity) - ($detail->discount ?? 0);

        // 4. Calcular márgenes
        $unitMargin = $detail->unit_price - $unitCost;
        $totalMargin = $itemSubtotal - $totalCost;

        // 5. Calcular porcentaje (sobre el costo, no sobre el precio)
        $marginPercentage = $unitCost > 0
            ? (($detail->unit_price - $unitCost) / $unitCost) * 100
            : 0;

        // 6. Validar margen mínimo por categoría
        $this->validateMinimumMargin($marginPercentage, $detail);

        return [
            'unit_cost' => round($unitCost, 2),
            'total_cost' => round($totalCost, 2),
            'unit_margin' => round($unitMargin, 2),
            'total_margin' => round($totalMargin, 2),
            'margin_percentage' => round($marginPercentage, 2),
        ];
    }

    /**
     * Determina el costo unitario según el origen del producto
     */
    private function determineUnitCost(QuotationDetail $detail): float
    {
        // CASO 1: Producto de almacén - usar costo promedio ponderado
        if ($detail->source_type === 'warehouse') {
            return $this->getWarehouseWeightedAverageCost($detail);
        }

        // CASO 2: Producto de proveedor específico
        if ($detail->source_type === 'supplier' && $detail->supplier_product_id) {
            return $detail->supplierProduct?->supplier_price ?? $detail->purchase_price ?? 0;
        }

        // CASO 3: Fallback - usar purchase_price del detail
        return $detail->purchase_price ?? 0;
    }

    /**
     * Calcula el costo promedio ponderado desde los lotes (purchase_batches)
     */
    private function getWarehouseWeightedAverageCost(QuotationDetail $detail): float
    {
        // Buscar lotes activos del producto en el almacén
        $batches = DB::table('purchase_batches')
            ->where('product_id', $detail->product_id)
            ->where('warehouse_id', $detail->warehouse_id)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->select('purchase_price', 'quantity_available')
            ->get();

        if ($batches->isEmpty()) {
            // Si no hay lotes, intentar con distribution_price del producto
            $product = Product::find($detail->product_id);
            return $product?->distribution_price ?? $detail->purchase_price ?? 0;
        }

        // Calcular promedio ponderado
        $totalCost = 0;
        $totalQuantity = 0;

        foreach ($batches as $batch) {
            $totalCost += $batch->purchase_price * $batch->quantity_available;
            $totalQuantity += $batch->quantity_available;
        }

        return $totalQuantity > 0 ? ($totalCost / $totalQuantity) : 0;
    }

    /**
     * Valida que el margen cumpla con el mínimo de la categoría del producto
     */
    private function validateMinimumMargin(float $marginPercentage, QuotationDetail $detail): void
    {
        // Cargar categoría con sus padres
        $product = Product::with('category.parent.parent')->find($detail->product_id);

        if (!$product || !$product->category) {
            // Si no tiene categoría, usar default del sistema
            $minMargin = $this->settingService->get('margins', 'min_margin_percentage', 10);
        } else {
            // ✅ Usar método de herencia
            $minMargin = $product->category->getEffectiveMinMargin();
        }

        $alertLowMargin = $this->settingService->get('margins', 'alert_low_margin', true);

        if ($alertLowMargin && $marginPercentage < $minMargin) {
            $categoryName = $product?->category?->name ?? 'la categoría';

            throw new LowMarginException(
                "El producto '{$detail->product_name}' tiene un margen de " . round($marginPercentage, 2) . "% " .
                    "que está por debajo del mínimo permitido para {$categoryName} ({$minMargin}%)",
                $marginPercentage,
                $minMargin,
                $detail->product_id
            );
        }
    }

    /**
     * Calcula márgenes totales de toda la cotización
     */
    public function calculateQuotationTotalMargin($quotationDetails): array
    {
        $totalCost = $quotationDetails->sum('total_cost');
        $totalPrice = $quotationDetails->sum('subtotal'); // Usar subtotal, no total (que incluye IGV)
        $totalMargin = $totalPrice - $totalCost;

        $marginPercentage = $totalCost > 0
            ? ($totalMargin / $totalCost) * 100
            : 0;

        return [
            'total_margin' => round($totalMargin, 2),
            'margin_percentage' => round($marginPercentage, 2),
        ];
    }

    /**
     * Calcula el precio sugerido según margen objetivo
     */
    public function calculateSuggestedPrice(float $cost, float $targetMarginPercentage): float
    {
        // Precio = Costo * (1 + Margen/100)
        return $cost * (1 + ($targetMarginPercentage / 100));
    }

    /**
     * Valida si un precio propuesto cumple con el margen mínimo
     */
    public function validateProposedPrice(
        float $proposedPrice,
        float $cost,
        int $productId
    ): array {
        $product = Product::with('category')->find($productId);

        $minMargin = $product?->category?->min_margin_percentage
            ?? $this->settingService->get('margins', 'min_margin_percentage', 10);

        $calculatedMargin = $cost > 0
            ? (($proposedPrice - $cost) / $cost) * 100
            : 0;

        $isValid = $calculatedMargin >= $minMargin;
        $suggestedMinPrice = $this->calculateSuggestedPrice($cost, $minMargin);

        return [
            'is_valid' => $isValid,
            'proposed_price' => round($proposedPrice, 2),
            'cost' => round($cost, 2),
            'calculated_margin' => round($calculatedMargin, 2),
            'minimum_required_margin' => $minMargin,
            'suggested_min_price' => round($suggestedMinPrice, 2),
            'category' => $product?->category?->name,
        ];
    }
}

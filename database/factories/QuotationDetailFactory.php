<?php

namespace Database\Factories;

use App\Models\QuotationDetail;
use App\Models\Quotation;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Entity;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuotationDetail>
 */
class QuotationDetailFactory extends Factory
{
    protected $model = QuotationDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $purchasePrice = $this->faker->randomFloat(2, 50, 500);
        $distributionPrice = $purchasePrice * 1.15; // 15% más
        $unitPrice = $distributionPrice * 1.30; // 30% de margen
        $discount = $this->faker->optional(0.3)->randomFloat(2, 0, $unitPrice * $quantity * 0.1);
        
        $subtotal = ($unitPrice * $quantity) - ($discount ?? 0);
        $taxAmount = $subtotal * 0.18;
        $total = $subtotal + $taxAmount;
        
        $unitCost = $distributionPrice;
        $totalCost = $unitCost * $quantity;
        $unitMargin = $unitPrice - $unitCost;
        $totalMargin = $subtotal - $totalCost;
        $marginPercentage = ($unitMargin / $unitCost) * 100;

        return [
            'quotation_id' => Quotation::factory(),
            'product_id' => Product::factory(),
            
            // Snapshot del producto
            'product_name' => $this->faker->words(3, true),
            'product_sku' => 'SKU-' . strtoupper($this->faker->bothify('???-###')),
            'product_brand' => $this->faker->optional()->company(),
            
            // Cantidad y precios
            'quantity' => $quantity,
            'purchase_price' => $purchasePrice,
            'distribution_price' => $distributionPrice,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'discount_percentage' => $discount ? (($discount / ($unitPrice * $quantity)) * 100) : 0,
            
            // Subtotales e impuestos
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            
            // Costos y márgenes
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'unit_margin' => $unitMargin,
            'total_margin' => $totalMargin,
            'margin_percentage' => $marginPercentage,
            
            // Origen del producto
            'source_type' => 'warehouse',
            'warehouse_id' => Warehouse::factory(),
            'supplier_id' => null,
            'supplier_product_id' => null,
            'is_requested_from_supplier' => false,
            
            // Proveedor sugerido
            'suggested_supplier_id' => null,
            'supplier_price' => null,
            
            // Estado de stock
            'available_stock' => $this->faker->numberBetween(10, 100),
            'in_stock' => true,
            
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the item comes from warehouse.
     */
    public function fromWarehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'warehouse',
            'warehouse_id' => Warehouse::factory(),
            'supplier_id' => null,
            'supplier_product_id' => null,
            'is_requested_from_supplier' => false,
            'in_stock' => true,
            'available_stock' => $this->faker->numberBetween(10, 100),
        ]);
    }

    /**
     * Indicate that the item comes from supplier.
     */
    public function fromSupplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => 'supplier',
            'warehouse_id' => null,
            'supplier_id' => Entity::factory()->supplier(),
            'supplier_product_id' => SupplierProduct::factory(),
            'is_requested_from_supplier' => $this->faker->boolean(70),
            'in_stock' => false,
            'available_stock' => 0,
        ]);
    }

    /**
     * Indicate that the item is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_stock' => false,
            'available_stock' => 0,
        ]);
    }

    /**
     * Indicate that the item has low margin.
     */
    public function lowMargin(): static
    {
        return $this->state(function (array $attributes) {
            $purchasePrice = $attributes['purchase_price'];
            $unitPrice = $purchasePrice * 1.05; // Solo 5% de margen
            $quantity = $attributes['quantity'];
            $discount = $attributes['discount'] ?? 0;
            
            $subtotal = ($unitPrice * $quantity) - $discount;
            $taxAmount = $subtotal * 0.18;
            $total = $subtotal + $taxAmount;
            
            $unitCost = $purchasePrice;
            $totalCost = $unitCost * $quantity;
            $unitMargin = $unitPrice - $unitCost;
            $totalMargin = $subtotal - $totalCost;
            $marginPercentage = ($unitMargin / $unitCost) * 100;
            
            return [
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'unit_margin' => $unitMargin,
                'total_margin' => $totalMargin,
                'margin_percentage' => $marginPercentage,
            ];
        });
    }

    /**
     * Indicate that the item has high margin.
     */
    public function highMargin(): static
    {
        return $this->state(function (array $attributes) {
            $purchasePrice = $attributes['purchase_price'];
            $unitPrice = $purchasePrice * 1.50; // 50% de margen
            $quantity = $attributes['quantity'];
            $discount = $attributes['discount'] ?? 0;
            
            $subtotal = ($unitPrice * $quantity) - $discount;
            $taxAmount = $subtotal * 0.18;
            $total = $subtotal + $taxAmount;
            
            $unitCost = $purchasePrice;
            $totalCost = $unitCost * $quantity;
            $unitMargin = $unitPrice - $unitCost;
            $totalMargin = $subtotal - $totalCost;
            $marginPercentage = ($unitMargin / $unitCost) * 100;
            
            return [
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'unit_margin' => $unitMargin,
                'total_margin' => $totalMargin,
                'margin_percentage' => $marginPercentage,
            ];
        });
    }

    /**
     * Indicate that the item has a discount.
     */
    public function withDiscount(float $discountAmount): static
    {
        return $this->state(function (array $attributes) use ($discountAmount) {
            $unitPrice = $attributes['unit_price'];
            $quantity = $attributes['quantity'];
            
            $subtotal = ($unitPrice * $quantity) - $discountAmount;
            $taxAmount = $subtotal * 0.18;
            $total = $subtotal + $taxAmount;
            
            $totalCost = $attributes['total_cost'];
            $totalMargin = $subtotal - $totalCost;
            
            return [
                'discount' => $discountAmount,
                'discount_percentage' => ($discountAmount / ($unitPrice * $quantity)) * 100,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'total_margin' => $totalMargin,
            ];
        });
    }
}
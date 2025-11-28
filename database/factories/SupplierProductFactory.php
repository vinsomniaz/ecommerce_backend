<?php

namespace Database\Factories;

use App\Models\SupplierProduct;
use App\Models\Entity;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplierProduct>
 */
class SupplierProductFactory extends Factory
{
    protected $model = SupplierProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchasePrice = $this->faker->randomFloat(2, 50, 2000);
        $distributionPrice = $purchasePrice * 1.15; // 15% más que compra

        return [
            'supplier_id' => Entity::factory()->supplier(),
            'product_id' => Product::factory(),
            
            // SKU del proveedor
            'supplier_sku' => strtoupper($this->faker->bothify('SUP-???-####')),
            
            // Precios
            'purchase_price' => $purchasePrice,
            'distribution_price' => $distributionPrice,
            'currency' => $this->faker->randomElement(['PEN', 'USD']),
            
            // Stock y disponibilidad
            'available_stock' => $this->faker->numberBetween(0, 500),
            'is_available' => $this->faker->boolean(85), // 85% disponible
            'is_active' => true,
            
            // Tiempos y cantidades
            'delivery_days' => $this->faker->numberBetween(1, 15),
            'min_order_quantity' => $this->faker->randomElement([1, 5, 10, 20]),
            
            // Prioridad (mayor = mejor proveedor)
            'priority' => $this->faker->numberBetween(1, 10),
            
            // Fechas
            'last_purchase_date' => $this->faker->optional(0.6)->dateTimeBetween('-6 months', 'now'),
            'last_purchase_price' => $this->faker->optional()->randomFloat(2, 50, 2000),
            'price_updated_at' => now()->subDays($this->faker->numberBetween(0, 30)),
            
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the supplier product is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => true,
            'available_stock' => $this->faker->numberBetween(10, 500),
        ]);
    }

    /**
     * Indicate that the supplier product is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
            'available_stock' => 0,
        ]);
    }

    /**
     * Indicate that the supplier product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the supplier product is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(8, 10),
        ]);
    }

    /**
     * Indicate that the supplier product is low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * Indicate that the supplier product is in PEN currency.
     */
    public function inPEN(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'PEN',
        ]);
    }

    /**
     * Indicate that the supplier product is in USD currency.
     */
    public function inUSD(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USD',
        ]);
    }

    /**
     * Indicate that the supplier product has fast delivery.
     */
    public function fastDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_days' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * Indicate that the supplier product has slow delivery.
     */
    public function slowDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_days' => $this->faker->numberBetween(10, 30),
        ]);
    }

    /**
     * Indicate that the supplier product has a recent purchase.
     */
    public function recentlyPurchased(): static
    {
        $purchasePrice = $this->faker->randomFloat(2, 50, 2000);
        
        return $this->state(fn (array $attributes) => [
            'last_purchase_date' => now()->subDays($this->faker->numberBetween(1, 15)),
            'last_purchase_price' => $purchasePrice,
        ]);
    }

    /**
     * Set a specific supplier by name.
     */
    public function forSupplier(string $supplierName): static
    {
        return $this->state(function (array $attributes) use ($supplierName) {
            $supplier = Entity::factory()->supplier()->create([
                'business_name' => $supplierName,
                'trade_name' => strtolower($supplierName),
            ]);
            
            return [
                'supplier_id' => $supplier->id,
            ];
        });
    }

    /**
     * Set a specific price.
     */
    public function withPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_price' => $price,
            'distribution_price' => $price * 1.15,
        ]);
    }

    /**
     * Set a specific stock amount.
     */
    public function withStock(int $stock): static
    {
        return $this->state(fn (array $attributes) => [
            'available_stock' => $stock,
            'is_available' => $stock > 0,
        ]);
    }
}
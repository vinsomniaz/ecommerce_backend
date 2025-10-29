<?php
// database/factories/Supports/PurchaseBatchFactory.php

namespace Database\Factories\Supports;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Supports\PurchaseBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseBatchFactory extends Factory
{
    protected $model = PurchaseBatch::class;

    public function definition(): array
    {
        $purchasePrice = $this->faker->randomFloat(2, 10, 500);
        $quantity = $this->faker->numberBetween(10, 1000);

        return [
            'purchase_id' => null, // ✅ NULL por defecto (lote manual)
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'batch_code' => 'BATCH-' . $this->faker->unique()->numerify('######'),
            'quantity_purchased' => $quantity,
            'quantity_available' => $quantity,
            'purchase_price' => $purchasePrice,
            'distribution_price' => round($purchasePrice * 1.2, 2),
            'purchase_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'expiry_date' => $this->faker->optional(0.3)->dateTimeBetween('now', '+2 years'),
            'status' => 'active',
        ];
    }

    /**
     * Lote activo
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Lote inactivo/agotado
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Lote sin stock disponible
     */
    public function depleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_available' => 0,
        ]);
    }

    /**
     * Lote con stock específico
     */
    public function withStock(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_purchased' => $quantity,
            'quantity_available' => $quantity,
        ]);
    }

    /**
     * Lote con precios específicos
     */
    public function withPrices(float $purchase, float $distribution): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_price' => $purchase,
            'distribution_price' => $distribution,
        ]);
    }

    /**
     * Lote para producto específico
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    /**
     * Lote en almacén específico
     */
    public function inWarehouse(Warehouse $warehouse): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_id' => $warehouse->id,
        ]);
    }

    /**
     * Lote con fecha de expiración
     */
    public function withExpiryDate(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => $date,
        ]);
    }

    /**
     * Lote sin fecha de expiración
     */
    public function withoutExpiryDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => null,
        ]);
    }
}

<?php
// database/factories/InventoryFactory.php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'available_stock' => $this->faker->numberBetween(0, 1000),
            'reserved_stock' => 0,
            'sale_price' => $this->faker->randomFloat(2, 50, 500),
            'profit_margin' => $this->faker->randomFloat(2, 10, 50),
        ];
    }

    public function withStock(int $stock): static
    {
        return $this->state(['available_stock' => $stock]);
    }

    public function withoutStock(): static
    {
        return $this->state(['available_stock' => 0]);
    }
}

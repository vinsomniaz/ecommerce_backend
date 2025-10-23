<?php

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
            'available_stock' => $this->faker->numberBetween(0, 100),
            'reserved_stock' => $this->faker->numberBetween(0, 20),
            'precio_venta' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}

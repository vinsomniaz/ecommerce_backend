<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Warehouse',
            'ubigeo' => '150101',
            'address' => $this->faker->address,
            'phone' => $this->faker->optional()->phoneNumber,
            'is_main' => false,
            'is_active' => true,
            'visible_online' => true,
            'picking_priority' => $this->faker->numberBetween(0, 10),
        ];
    }
}

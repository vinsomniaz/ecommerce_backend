<?php
// database/factories/WarehouseFactory.php

namespace Database\Factories;

use App\Models\Ubigeo;
use App\Models\Country;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        // ✅ Asegurar que Country existe
        $country = Country::firstOrCreate(
            ['code' => 'PE'],
            ['name' => 'Perú', 'phone_code' => '+51']
        );

        // ✅ Asegurar que Ubigeo existe
        $ubigeo = Ubigeo::firstOrCreate(
            ['ubigeo' => '150101'],
            [
                'country_code' => 'PE',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'LIMA',
                'codigo_sunat' => '150101',
            ]
        );

        return [
            'name' => $this->faker->company . ' Warehouse',
            'ubigeo' => $ubigeo->ubigeo,
            'address' => $this->faker->address,
            'phone' => $this->faker->optional()->phoneNumber,
            'is_active' => true,
            'visible_online' => true,
            'picking_priority' => $this->faker->numberBetween(0, 10),
            'is_main' => false,
        ];
    }

    public function main(): static
    {
        return $this->state(['is_main' => true]);
    }
}

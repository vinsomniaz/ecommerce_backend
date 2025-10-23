<?php

namespace Database\Factories;

use App\Models\Ubigeo;
use Illuminate\Database\Eloquent\Factories\Factory;

class UbigeoFactory extends Factory
{
    protected $model = Ubigeo::class;

    public function definition(): array
    {
        return [
            'ubigeo' => $this->faker->numerify('######'),
            'departamento' => $this->faker->state,
            'provincia' => $this->faker->city,
            'distrito' => $this->faker->city,
            'codigo_sunat' => $this->faker->optional()->numerify('######'),
        ];
    }
}

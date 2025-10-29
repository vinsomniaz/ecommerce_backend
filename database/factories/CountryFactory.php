<?php

// database/factories/CountryFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'PE',
            'name' => 'Perú',
            'phone_code' => '+51',
        ];
    }
}

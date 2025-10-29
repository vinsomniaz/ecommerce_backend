<?php
// database/factories/UbigeoFactory.php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Ubigeo;
use Illuminate\Database\Eloquent\Factories\Factory;

class UbigeoFactory extends Factory
{
    protected $model = Ubigeo::class;

    public function definition(): array
    {
        // ✅ IMPORTANTE: No crear dentro del factory, solo retornar valores
        return [
            'ubigeo' => '150101',
            'country_code' => 'PE',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
            'codigo_sunat' => '150101',
        ];
    }
}

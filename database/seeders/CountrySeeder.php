<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('countries')->delete();

        $countries = [
            ['code' => 'PE', 'name' => 'Perú', 'phone_code' => '+51'],
            ['code' => 'US', 'name' => 'Estados Unidos', 'phone_code' => '+1'],
            ['code' => 'ES', 'name' => 'España', 'phone_code' => '+34'],
            ['code' => 'AR', 'name' => 'Argentina', 'phone_code' => '+54'],
            ['code' => 'CL', 'name' => 'Chile', 'phone_code' => '+56'],
            ['code' => 'CO', 'name' => 'Colombia', 'phone_code' => '+57'],
            ['code' => 'MX', 'name' => 'México', 'phone_code' => '+52'],
            ['code' => 'BR', 'name' => 'Brasil', 'phone_code' => '+55'],
        ];

        foreach ($countries as $country) {
            Country::updateOrInsert(
                ['code' => $country['code']], // criterio de búsqueda
                ['name' => $country['name'], 'phone_code' => $country['phone_code']]
            );
        }
    }

}

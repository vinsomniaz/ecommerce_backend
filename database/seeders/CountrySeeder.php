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
            ['code' => 'PE', 'name' => 'Perú'],
            ['code' => 'US', 'name' => 'Estados Unidos'],
            ['code' => 'ES', 'name' => 'España'],
            ['code' => 'AR', 'name' => 'Argentina'],
            ['code' => 'CL', 'name' => 'Chile'],
            ['code' => 'CO', 'name' => 'Colombia'],
            ['code' => 'MX', 'name' => 'México'],
            ['code' => 'BR', 'name' => 'Brasil'],
        ];

        Country::insert($countries);
    }
}
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DefaultSettingsSeeder::class,
            UserSeeder::class,
            CountrySeeder::class, // Debe ir antes de UbigeoSeeder
            UbigeoSeeder::class,
            PriceListSeeder::class,
            CsvMigrationSeeder::class, // Primero crea los productos
            ProductImageSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PriceListSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            [
                'code' => 'RETAIL',
                'name' => 'Precio Minorista',
                'description' => 'Precio para venta al público general (tienda física y online)',
                'is_active' => true,
            ],
            [
                'code' => 'WHOLESALE',
                'name' => 'Precio Mayorista',
                'description' => 'Precio para compras en volumen (10+ unidades)',
                'is_active' => true,
            ],
            [
                'code' => 'DISTRIBUTOR',
                'name' => 'Precio Distribuidor',
                'description' => 'Precio para distribuidores autorizados',
                'is_active' => true,
            ],
            [
                'code' => 'MIN_RETAIL',
                'name' => 'Precio Mínimo Minorista',
                'description' => 'Precio mínimo permitido para venta minorista',
                'is_active' => true,
            ],
        ];

        foreach ($lists as $list) {
            DB::table('price_lists')->insert(array_merge($list, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@datastore.local',
            'password' => bcrypt('password123'),
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        $user->assignRole('super-admin');
    }
}

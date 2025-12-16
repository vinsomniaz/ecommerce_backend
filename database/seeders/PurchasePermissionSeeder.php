<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PurchasePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'purchases.index',
            'purchases.store',
            'purchases.show',
            'purchases.update',
            'purchases.destroy',
            'purchases.statistics',
            'purchases.payments.create'
        ];

        $guards = ['web', 'api', 'sanctum'];

        foreach ($permissions as $perm) {
            foreach ($guards as $guard) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => $guard]);
            }
        }

        // Assign to super-admin
        $role = Role::where('name', 'super-admin')->where('guard_name', 'sanctum')->first();
        if ($role) {
            $role->givePermissionTo(Permission::whereIn('name', $permissions)->where('guard_name', 'sanctum')->get());
        }

        // Also check api/web roles if they exist
        $roleWeb = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();
        if ($roleWeb) {
            $roleWeb->givePermissionTo(Permission::whereIn('name', $permissions)->where('guard_name', 'web')->get());
        }
    }
}

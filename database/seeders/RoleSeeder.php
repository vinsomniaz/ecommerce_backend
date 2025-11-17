<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Resetear cache de roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ========================================
        // CREAR ROLES
        // ========================================
        $superAdmin = Role::create(['name' => 'super-admin']);
        $admin = Role::create(['name' => 'admin']);
        $vendor = Role::create(['name' => 'vendor']); // Vendedor
        $customer = Role::create(['name' => 'customer']); // Cliente

        // ========================================
        // CREAR PERMISOS (ejemplos básicos)
        // ========================================

        // Productos
        Permission::create(['name' => 'products.view']);
        Permission::create(['name' => 'products.create']);
        Permission::create(['name' => 'products.edit']);
        Permission::create(['name' => 'products.delete']);

        // Inventario
        Permission::create(['name' => 'inventory.view']);
        Permission::create(['name' => 'inventory.manage']);

        // Ventas
        Permission::create(['name' => 'sales.view']);
        Permission::create(['name' => 'sales.create']);

        // Compras
        Permission::create(['name' => 'purchases.view']);
        Permission::create(['name' => 'purchases.create']);

        // Clientes
        Permission::create(['name' => 'customers.view']);
        Permission::create(['name' => 'customers.manage']);

        // Reportes
        Permission::create(['name' => 'reports.view']);

        // ========================================
        // ASIGNAR PERMISOS A ROLES
        // ========================================

        // Super Admin - todos
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - casi todos
        $admin->givePermissionTo([
            'products.view', 'products.create', 'products.edit',
            'inventory.view', 'inventory.manage',
            'sales.view', 'sales.create',
            'purchases.view', 'purchases.create',
            'customers.view', 'customers.manage',
            'reports.view',
        ]);

        // Vendor - solo ventas e inventario básico
        $vendor->givePermissionTo([
            'products.view',
            'inventory.view',
            'sales.view', 'sales.create',
            'customers.view',
        ]);

        // Customer - ninguno (solo sus propios datos)
        // Los permisos de customer se manejan en políticas
    }
}

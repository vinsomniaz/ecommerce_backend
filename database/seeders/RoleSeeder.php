<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Resetear cache de roles y permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ========================================
        // CREAR PERMISOS GRANULARES (idempotente)
        // ========================================

        $permissions = [
            // === CATEGORIAS ===
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            // === PRODUCTOS ===
            'products.view',
            'products.create',
            'products.update',
            'products.delete',

            // === INVENTARIO (SCOPE POR ALMACÉN) ===
            'inventory.view.own-warehouse',
            'inventory.view.all-warehouses',
            'inventory.manage.own-warehouse',
            'inventory.manage.all-warehouses',

            // === VENTAS (SCOPE POR ALMACÉN) ===
            'sales.view.own',
            'sales.view.own-warehouse',
            'sales.view.all-warehouses',
            'sales.create.own-warehouse',
            'sales.create.all-warehouses',
            'sales.update',
            'sales.delete',

            // === COMPRAS ===
            'purchases.view',
            'purchases.create',
            'purchases.update',
            'purchases.delete',

            // === CLIENTES ===
            'customers.view',
            'customers.manage',

            // === USUARIOS ===
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // === ALMACENES ===
            'warehouses.view',
            'warehouses.create',
            'warehouses.update',
            'warehouses.delete',

            // === REPORTES (SCOPE POR ALMACÉN) ===
            'reports.view.own-warehouse',
            'reports.view.all-warehouses',

            // === PERMISOS (SOLO SUPER-ADMIN) ===
            'permissions.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm], ['guard_name' => 'web']);
        }

        // ========================================
        // CREAR ROLES (idempotente)
        // ========================================
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin'], ['guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        $vendor     = Role::firstOrCreate(['name' => 'vendor'], ['guard_name' => 'web']);
        $customer   = Role::firstOrCreate(['name' => 'customer'], ['guard_name' => 'web']);

        // ========================================
        // ASIGNAR PERMISOS A ROLES
        // ========================================

        // SUPER ADMIN - TODOS los permisos
        $superAdmin->syncPermissions(Permission::all());

        // ADMIN - Gestión completa excepto permisos
        $admin->syncPermissions([
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'inventory.view.all-warehouses',
            'inventory.manage.all-warehouses',
            'sales.view.all-warehouses',
            'sales.create.all-warehouses',
            'sales.update',
            'sales.delete',
            'purchases.view',
            'purchases.create',
            'purchases.update',
            'purchases.delete',
            'customers.view',
            'customers.manage',
            'users.view',
            'users.create',
            'users.update',
            'warehouses.view',
            'warehouses.create',
            'warehouses.update',
            'reports.view.all-warehouses',
        ]);

        // VENDOR - Solo su almacén
        $vendor->syncPermissions([
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            'products.view',
            'inventory.view.own-warehouse',
            'sales.view.own',
            'sales.view.own-warehouse',
            'sales.create.own-warehouse',
            'customers.view',
            'reports.view.own-warehouse',
        ]);

        // CUSTOMER - Sin permisos directos (se manejan con policies / guards públicos)
        $customer->syncPermissions([]);
    }
}

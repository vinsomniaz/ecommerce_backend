<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🔄 Iniciando creación de roles y permisos...');

        DB::beginTransaction();

        try {
            $this->command->info('📝 Creando permisos...');
            $this->createAllPermissions();

            $this->command->info('👥 Creando/actualizando roles...');

            $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'sanctum']);
            $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
            $vendor = Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'sanctum']);
            $customer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'sanctum']);

            $this->command->info('🔄 Sincronizando permisos con roles...');

            $superAdmin->syncPermissions(Permission::all());
            $this->command->info("✅ Super-Admin: " . $superAdmin->permissions->count() . " permisos");

            $adminPermissions = $this->getAdminPermissions();
            $admin->syncPermissions($adminPermissions);
            $this->command->info("✅ Admin: " . count($adminPermissions) . " permisos");

            $vendorPermissions = $this->getVendorPermissions();
            $vendor->syncPermissions($vendorPermissions);
            $this->command->info("✅ Vendor: " . count($vendorPermissions) . " permisos");

            $customerPermissions = $this->getCustomerPermissions();
            $customer->syncPermissions($customerPermissions);
            $this->command->info("✅ Customer: " . count($customerPermissions) . " permisos");

            DB::commit();

            $this->command->newLine();
            $this->command->info('✅ ¡Roles y permisos creados exitosamente!');
            $this->command->info("📊 Total de permisos: " . Permission::count());
            $this->command->info("🔐 Roles configurados: 4");
            $this->command->newLine();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createAllPermissions(): void
    {
        $permissionsByModule = [

            'Categories' => [
                ['name' => 'categories.index', 'display_name' => 'Listar categorías', 'description' => 'Obtiene el listado de todas las categorías registradas.'],
                ['name' => 'categories.tree', 'display_name' => 'Árbol de categorías', 'description' => 'Obtiene la estructura de categorías en formato árbol.'],
                ['name' => 'categories.show', 'display_name' => 'Ver categoría', 'description' => 'Muestra el detalle de una categoría específica.'],
                ['name' => 'categories.store', 'display_name' => 'Crear categoría', 'description' => 'Registra una nueva categoría en el sistema.'],
                ['name' => 'categories.update', 'display_name' => 'Actualizar categoría', 'description' => 'Actualiza los datos de una categoría existente.'],
                ['name' => 'categories.destroy', 'display_name' => 'Eliminar categoría', 'description' => 'Elimina una categoría del sistema.'],
            ],

            'Warehouses' => [
                ['name' => 'warehouses.index', 'display_name' => 'Listar almacenes', 'description' => 'Obtiene el listado de todos los almacenes.'],
                ['name' => 'warehouses.show', 'display_name' => 'Ver almacén', 'description' => 'Muestra el detalle de un almacén específico.'],
                ['name' => 'warehouses.store', 'display_name' => 'Crear almacén', 'description' => 'Registra un nuevo almacén.'],
                ['name' => 'warehouses.update', 'display_name' => 'Actualizar almacén', 'description' => 'Actualiza los datos de un almacén existente.'],
                ['name' => 'warehouses.destroy', 'display_name' => 'Eliminar almacén', 'description' => 'Elimina un almacén del sistema.'],
                ['name' => 'warehouses.inventory', 'display_name' => 'Inventario de almacén', 'description' => 'Consulta el inventario asociado a un almacén específico.'],
                ['name' => 'warehouses.inventory.statistics', 'display_name' => 'Estadísticas de almacén', 'description' => 'Obtiene estadísticas de inventario por almacén.'],
            ],

            // 🔥 NUEVO: Permisos específicos de acceso a almacenes
            'Warehouse Access' => [
                ['name' => 'warehouses.view.all', 'display_name' => 'Ver todos los almacenes', 'description' => 'Permite acceder a información de cualquier almacén del sistema.'],
                ['name' => 'warehouses.view.own', 'display_name' => 'Ver solo su almacén asignado', 'description' => 'Solo puede acceder a su warehouse_id asignado.'],
                ['name' => 'warehouses.manage.all', 'display_name' => 'Gestionar todos los almacenes', 'description' => 'Permite crear, editar y eliminar cualquier almacén.'],
            ],

            'Products' => [
                ['name' => 'products.index', 'display_name' => 'Listar productos', 'description' => 'Obtiene el listado de todos los productos.'],
                ['name' => 'products.show', 'display_name' => 'Ver producto', 'description' => 'Muestra el detalle de un producto específico.'],
                ['name' => 'products.store', 'display_name' => 'Crear producto', 'description' => 'Registra un nuevo producto en el catálogo.'],
                ['name' => 'products.update', 'display_name' => 'Actualizar producto', 'description' => 'Actualiza la información de un producto existente.'],
                ['name' => 'products.destroy', 'display_name' => 'Eliminar producto', 'description' => 'Elimina un producto del catálogo.'],
                ['name' => 'products.restore', 'display_name' => 'Restaurar producto', 'description' => 'Restaura un producto previamente eliminado.'],
                ['name' => 'products.bulk-update', 'display_name' => 'Actualización masiva de productos', 'description' => 'Actualiza múltiples productos en una sola operación.'],
                ['name' => 'products.statistics', 'display_name' => 'Estadísticas de productos', 'description' => 'Consulta estadísticas generales del catálogo de productos.'],
                ['name' => 'products.duplicate', 'display_name' => 'Duplicar producto', 'description' => 'Crea una copia de un producto existente.'],
                ['name' => 'products.images.upload', 'display_name' => 'Subir imágenes de producto', 'description' => 'Adjunta o sube imágenes a un producto.'],
                ['name' => 'products.images.delete', 'display_name' => 'Eliminar imágenes de producto', 'description' => 'Elimina imágenes asociadas a un producto.'],
                ['name' => 'products.inventory', 'display_name' => 'Inventario por producto', 'description' => 'Consulta el inventario asociado a un producto específico.'],
                ['name' => 'products.inventory.statistics', 'display_name' => 'Estadísticas de inventario por producto', 'description' => 'Obtiene estadísticas de stock para un producto.'],
            ],

            'Product Attributes' => [
                ['name' => 'attributes.index', 'display_name' => 'Listar atributos de producto', 'description' => 'Obtiene la lista de atributos asociados a productos.'],
                ['name' => 'attributes.store', 'display_name' => 'Crear atributo de producto', 'description' => 'Crea un nuevo atributo para un producto.'],
                ['name' => 'attributes.update', 'display_name' => 'Actualizar atributo de producto', 'description' => 'Actualiza la información de un atributo de producto.'],
                ['name' => 'attributes.destroy', 'display_name' => 'Eliminar atributo de producto', 'description' => 'Elimina un atributo asociado a un producto.'],
                ['name' => 'attributes.bulk-update', 'display_name' => 'Actualización masiva de atributos', 'description' => 'Actualiza múltiples atributos de producto en bloque.'],
            ],

            'Inventory' => [
                ['name' => 'inventory.index', 'display_name' => 'Listado de inventario', 'description' => 'Obtiene el listado general de inventario.'],
                ['name' => 'inventory.show', 'display_name' => 'Ver inventario', 'description' => 'Muestra el detalle de inventario de un producto en un almacén.'],
                ['name' => 'inventory.store', 'display_name' => 'Registrar inventario', 'description' => 'Crea un nuevo registro de inventario.'],
                ['name' => 'inventory.update', 'display_name' => 'Actualizar inventario', 'description' => 'Actualiza un registro existente de inventario.'],
                ['name' => 'inventory.destroy', 'display_name' => 'Eliminar inventario', 'description' => 'Elimina un registro de inventario.'],
                ['name' => 'inventory.bulk-assign', 'display_name' => 'Asignación masiva de inventario', 'description' => 'Asigna stock a varios productos de forma masiva.'],
                ['name' => 'inventory.statistics.global', 'display_name' => 'Estadísticas globales de inventario', 'description' => 'Obtiene estadísticas generales del inventario global.'],
                ['name' => 'inventory.alerts.low-stock', 'display_name' => 'Alertas de stock bajo', 'description' => 'Lista productos con nivel de stock bajo.'],
                ['name' => 'inventory.alerts.out-of-stock', 'display_name' => 'Alertas de stock agotado', 'description' => 'Lista productos sin stock disponible.'],
            ],

            // 🔥 NUEVO: Permisos específicos de acceso a inventario
            'Inventory Access' => [
                ['name' => 'inventory.view.all-warehouses', 'display_name' => 'Ver inventario de todos los almacenes', 'description' => 'Puede consultar inventario de cualquier almacén sin restricción.'],
                ['name' => 'inventory.view.own-warehouse', 'display_name' => 'Ver inventario de su almacén', 'description' => 'Solo puede consultar inventario de su warehouse_id asignado.'],
                ['name' => 'inventory.manage.all-warehouses', 'display_name' => 'Gestionar inventario de todos los almacenes', 'description' => 'Puede modificar inventario de cualquier almacén.'],
                ['name' => 'inventory.manage.own-warehouse', 'display_name' => 'Gestionar inventario de su almacén', 'description' => 'Solo puede modificar inventario de su warehouse_id.'],
            ],

            'Stock Management' => [
                ['name' => 'stock.transfer', 'display_name' => 'Transferir stock entre almacenes', 'description' => 'Realiza transferencias de stock entre almacenes.'],
                ['name' => 'stock.adjustment.in', 'display_name' => 'Ajuste de stock (entrada)', 'description' => 'Registra ajustes de entrada de stock manual.'],
                ['name' => 'stock.adjustment.out', 'display_name' => 'Ajuste de stock (salida)', 'description' => 'Registra ajustes de salida de stock manual.'],
                ['name' => 'stock.batches', 'display_name' => 'Consultar lotes disponibles', 'description' => 'Consulta los lotes de stock disponibles para un producto.'],
                ['name' => 'stock.movements', 'display_name' => 'Ver movimientos de stock', 'description' => 'Lista los movimientos de stock asociados a un producto o almacén.'],
                ['name' => 'stock.sync', 'display_name' => 'Sincronizar inventario', 'description' => 'Sincroniza el inventario con los movimientos de stock.'],
            ],

            // 🔥 NUEVO: Permisos específicos de transferencias
            'Stock Transfer Access' => [
                ['name' => 'stock.transfer.any', 'display_name' => 'Transferir entre cualquier almacén', 'description' => 'Puede hacer transferencias entre cualquier par de almacenes.'],
                ['name' => 'stock.transfer.own', 'display_name' => 'Transferir desde/hacia su almacén', 'description' => 'Solo puede transferir si origen o destino es su warehouse_id.'],
            ],

            'Entities' => [
                ['name' => 'entities.index', 'display_name' => 'Listar entidades', 'description' => 'Obtiene el listado de entidades (clientes, proveedores, etc.).'],
                ['name' => 'entities.show', 'display_name' => 'Ver entidad', 'description' => 'Muestra el detalle de una entidad específica.'],
                ['name' => 'entities.store', 'display_name' => 'Crear entidad', 'description' => 'Registra una nueva entidad en el sistema.'],
                ['name' => 'entities.update', 'display_name' => 'Actualizar entidad', 'description' => 'Actualiza la información de una entidad.'],
                ['name' => 'entities.destroy', 'display_name' => 'Eliminar entidad', 'description' => 'Elimina una entidad del sistema.'],
                ['name' => 'entities.deactivate', 'display_name' => 'Desactivar entidad', 'description' => 'Desactiva una entidad para que no pueda ser utilizada.'],
                ['name' => 'entities.activate', 'display_name' => 'Activar entidad', 'description' => 'Activa una entidad previamente desactivada.'],
                ['name' => 'entities.search', 'display_name' => 'Buscar entidades', 'description' => 'Permite buscar entidades por texto o filtros.'],
                ['name' => 'entities.find-by-document', 'display_name' => 'Buscar entidad por documento', 'description' => 'Busca una entidad por su documento (DNI, RUC, etc.).'],
            ],

            'Addresses' => [
                ['name' => 'addresses.index', 'display_name' => 'Listar direcciones', 'description' => 'Lista las direcciones asociadas a una entidad.'],
                ['name' => 'addresses.show', 'display_name' => 'Ver dirección', 'description' => 'Muestra el detalle de una dirección específica.'],
                ['name' => 'addresses.store', 'display_name' => 'Crear dirección', 'description' => 'Registra una nueva dirección para una entidad.'],
                ['name' => 'addresses.update', 'display_name' => 'Actualizar dirección', 'description' => 'Actualiza los datos de una dirección existente.'],
                ['name' => 'addresses.destroy', 'display_name' => 'Eliminar dirección', 'description' => 'Elimina una dirección de una entidad.'],
                ['name' => 'addresses.set-default', 'display_name' => 'Marcar dirección por defecto', 'description' => 'Define una dirección como predeterminada para una entidad.'],
            ],

            'Sunat' => [
                ['name' => 'sunat.validate-document', 'display_name' => 'Validar documento SUNAT/RENIEC', 'description' => 'Consulta a SUNAT/RENIEC para validar un documento (DNI/RUC).'],
            ],

            'Gemini AI' => [
                ['name' => 'gemini.generate-product-info', 'display_name' => 'Generar info de producto con IA', 'description' => 'Genera títulos y descripciones de producto usando Gemini.'],
                ['name' => 'gemini.generate-batch', 'display_name' => 'Generar fichas masivas con IA', 'description' => 'Genera información de múltiples productos en lote usando Gemini.'],
                ['name' => 'gemini.warm-cache', 'display_name' => 'Preparar caché de IA', 'description' => 'Precarga información o prompts en la caché de Gemini.'],
                ['name' => 'gemini.clear-cache', 'display_name' => 'Limpiar caché de IA', 'description' => 'Limpia o reinicia la caché de Gemini.'],
            ],

            'Users' => [
                ['name' => 'users.index', 'display_name' => 'Listar usuarios', 'description' => 'Obtiene el listado de usuarios del sistema.'],
                ['name' => 'users.show', 'display_name' => 'Ver usuario', 'description' => 'Muestra el detalle de un usuario concreto.'],
                ['name' => 'users.store', 'display_name' => 'Crear usuario', 'description' => 'Registra un nuevo usuario del sistema.'],
                ['name' => 'users.update', 'display_name' => 'Actualizar usuario', 'description' => 'Actualiza la información de un usuario.'],
                ['name' => 'users.destroy', 'display_name' => 'Eliminar usuario', 'description' => 'Elimina (soft delete) un usuario del sistema.'],
                ['name' => 'users.restore', 'display_name' => 'Restaurar usuario', 'description' => 'Restaura un usuario previamente eliminado.'],
                ['name' => 'users.toggle-active', 'display_name' => 'Activar/Desactivar usuario', 'description' => 'Cambia el estado activo/inactivo de un usuario.'],
                ['name' => 'users.change-role', 'display_name' => 'Cambiar rol de usuario', 'description' => 'Actualiza el rol principal asignado a un usuario.'],
            ],

            'Permissions' => [
                ['name' => 'permissions.index', 'display_name' => 'Listar permisos', 'description' => 'Obtiene todos los permisos disponibles en el sistema.'],
                ['name' => 'permissions.user', 'display_name' => 'Ver permisos de usuario', 'description' => 'Consulta los permisos asignados a un usuario.'],
                ['name' => 'permissions.assign', 'display_name' => 'Asignar permisos a usuario', 'description' => 'Asigna permisos adicionales a un usuario.'],
                ['name' => 'permissions.revoke', 'display_name' => 'Revocar permisos de usuario', 'description' => 'Revoca permisos asignados a un usuario.'],
                ['name' => 'permissions.sync', 'display_name' => 'Sincronizar permisos de usuario', 'description' => 'Reemplaza todos los permisos directos de un usuario.'],
                ['name' => 'permissions.suggestions', 'display_name' => 'Sugerencias de permisos', 'description' => 'Obtiene sugerencias de permisos recomendados para un rol.'],
            ],

            'Ecommerce' => [
                ['name' => 'ecommerce.products.index', 'display_name' => 'Listar productos e-commerce', 'description' => 'Obtiene el listado público de productos para la tienda online.'],
                ['name' => 'ecommerce.products.show', 'display_name' => 'Ver producto e-commerce', 'description' => 'Muestra el detalle público de un producto específico.'],
                ['name' => 'ecommerce.categories.list', 'display_name' => 'Listar categorías e-commerce', 'description' => 'Obtiene el listado de categorías visibles en la tienda online.'],
                ['name' => 'ecommerce.categories.tree', 'display_name' => 'Árbol de categorías e-commerce', 'description' => 'Obtiene la estructura de categorías públicas en formato árbol.'],
                ['name' => 'ecommerce.categories.show', 'display_name' => 'Ver categoría e-commerce', 'description' => 'Muestra el detalle público de una categoría específica.'],
            ],
        ];

        $totalCreated = 0;
        $totalUpdated = 0;

        foreach ($permissionsByModule as $module => $permissions) {
            foreach ($permissions as $perm) {
                $permission = Permission::updateOrCreate(
                    ['name' => $perm['name'], 'guard_name' => 'sanctum'],
                    [
                        'display_name' => $perm['display_name'],
                        'description'  => $perm['description'],
                        'module'       => $module,
                    ]
                );

                if ($permission->wasRecentlyCreated) {
                    $totalCreated++;
                } else {
                    $totalUpdated++;
                }
            }
        }

        if ($totalCreated > 0) {
            $this->command->info("   ✓ Creados: {$totalCreated} permisos nuevos");
        }
        if ($totalUpdated > 0) {
            $this->command->info("   ✓ Actualizados: {$totalUpdated} permisos existentes");
        }
    }

    /**
     * 🔥 ADMIN: Acceso total a todos los almacenes
     */
    private function getAdminPermissions(): array
    {
        return [
            // 🔥 ACCESO COMPLETO A ALMACENES
            'warehouses.view.all',
            'warehouses.manage.all',
            'inventory.view.all-warehouses',
            'inventory.manage.all-warehouses',
            'stock.transfer.any',

            // CATEGORIES
            'categories.index', 'categories.tree', 'categories.show',
            'categories.store', 'categories.update', 'categories.destroy',

            // WAREHOUSES
            'warehouses.index', 'warehouses.show', 'warehouses.store',
            'warehouses.update', 'warehouses.inventory', 'warehouses.inventory.statistics',

            // PRODUCTS
            'products.index', 'products.show', 'products.store', 'products.update',
            'products.destroy', 'products.restore', 'products.bulk-update',
            'products.statistics', 'products.duplicate', 'products.images.upload',
            'products.images.delete', 'products.inventory', 'products.inventory.statistics',

            // PRODUCT ATTRIBUTES
            'attributes.index', 'attributes.store', 'attributes.update',
            'attributes.destroy', 'attributes.bulk-update',

            // INVENTORY
            'inventory.index', 'inventory.show', 'inventory.store', 'inventory.update',
            'inventory.destroy', 'inventory.bulk-assign', 'inventory.statistics.global',
            'inventory.alerts.low-stock', 'inventory.alerts.out-of-stock',

            // STOCK
            'stock.transfer', 'stock.adjustment.in', 'stock.adjustment.out',
            'stock.batches', 'stock.movements',

            // ENTITIES
            'entities.index', 'entities.show', 'entities.store', 'entities.update',
            'entities.destroy', 'entities.deactivate', 'entities.activate',
            'entities.search', 'entities.find-by-document',

            // ADDRESSES
            'addresses.index', 'addresses.show', 'addresses.store',
            'addresses.update', 'addresses.destroy', 'addresses.set-default',

            // SUNAT
            'sunat.validate-document',

            // GEMINI
            'gemini.generate-product-info', 'gemini.generate-batch',

            // USERS
            'users.index', 'users.show', 'users.store',
            'users.update', 'users.toggle-active',

            // ECOMMERCE
            'ecommerce.products.index', 'ecommerce.products.show',
            'ecommerce.categories.list', 'ecommerce.categories.tree',
            'ecommerce.categories.show',
        ];
    }

    /**
     * 🔥 VENDOR: Solo acceso a su almacén asignado
     */
    private function getVendorPermissions(): array
    {
        return [
            // 🔥 ACCESO RESTRINGIDO A SU ALMACÉN
            'warehouses.view.own',
            'inventory.view.own-warehouse',
            'inventory.manage.own-warehouse',
            'stock.transfer.own',

            // CATEGORIES
            'categories.index', 'categories.tree', 'categories.show', 'categories.store',

            // WAREHOUSES (solo consulta)
            'warehouses.show', 'warehouses.inventory', 'warehouses.inventory.statistics',

            // PRODUCTS
            'products.index', 'products.show', 'products.statistics',
            'products.inventory', 'products.inventory.statistics',

            // PRODUCT ATTRIBUTES
            'attributes.index',

            // INVENTORY (de su almacén)
            'inventory.index', 'inventory.show',
            'inventory.alerts.low-stock', 'inventory.alerts.out-of-stock',

            // STOCK (de su almacén)
            'stock.transfer', 'stock.adjustment.in', 'stock.adjustment.out',
            'stock.batches', 'stock.movements',

            // ENTITIES
            'entities.index', 'entities.show', 'entities.store',
            'entities.update', 'entities.search', 'entities.find-by-document',

            // ADDRESSES
            'addresses.index', 'addresses.show', 'addresses.store',
            'addresses.update', 'addresses.destroy', 'addresses.set-default',

            // SUNAT
            'sunat.validate-document',

            // USERS
            'users.show', 'users.update',

            // ECOMMERCE
            'ecommerce.products.index', 'ecommerce.products.show',
            'ecommerce.categories.list', 'ecommerce.categories.tree',
            'ecommerce.categories.show',
        ];
    }

    /**
     * CUSTOMER: Solo acceso público
     */
    private function getCustomerPermissions(): array
    {
        return [
            // CATEGORIES
            'categories.index', 'categories.tree', 'categories.show',

            // PRODUCTS
            'products.index', 'products.show',

            // PRODUCT ATTRIBUTES
            'attributes.index',

            // ENTITIES (solo su información)
            'entities.show', 'entities.update',

            // ADDRESSES
            'addresses.index', 'addresses.show', 'addresses.store',
            'addresses.update', 'addresses.destroy', 'addresses.set-default',

            // SUNAT
            'sunat.validate-document',

            // USERS
            'users.show', 'users.update',

            // ECOMMERCE
            'ecommerce.products.index', 'ecommerce.products.show',
            'ecommerce.categories.list', 'ecommerce.categories.tree',
            'ecommerce.categories.show',
        ];
    }
}

<?php
// routes/api.php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductAttributeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\InventoryController; // NUEVO
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\SunatController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\StockManagementController;
use App\Http\Controllers\Api\EcommerceController;
use App\Http\Controllers\Api\GeminiController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS (Para el E-commerce)
|--------------------------------------------------------------------------
|
| Rutas de lectura (GET) que no requieren autenticación.
|
*/
// --- AÑADE ESTE GRUPO DE RUTAS PARA LA TIENDA ---
Route::prefix('ecommerce')->name('ecommerce/')->group(function () {

    // Ruta para la lista de productos: /api/ecommerce/products
    Route::get('products', [EcommerceController::class, 'index'])
        ->name('products.index');

    // Ruta para el detalle del producto: /api/ecommerce/products/{product}
    Route::get('products/{product}', [EcommerceController::class, 'show'])
        ->name('products.show');

    // --- NUEVAS RUTAS DE CATEGORÍAS PÚBLICAS ---
    Route::get('categories', [EcommerceController::class, 'listCategories'])
        ->name('categories.list');

    Route::get('categories/tree', [EcommerceController::class, 'getCategoryTree'])
        ->name('categories.tree');

    Route::get('categories/{id}', [EcommerceController::class, 'showCategory'])
        ->name('categories.show');
});

/*
|--------------------------------------------------------------------------
| RUTAS PRIVADAS
|--------------------------------------------------------------------------
|
*/

// ========================================
// RUTAS DE PERMISOS (Solo Super-Admin)
// ========================================
Route::middleware(['auth:sanctum', 'role:super-admin'])->prefix('permissions')->group(function () {

    // Listar todos los permisos disponibles agrupados por módulo
    Route::get('/', [PermissionController::class, 'index'])
        ->name('permissions.index');

    // 🔥 NUEVO: Listar permisos peligrosos/críticos
    Route::get('/dangerous', [PermissionController::class, 'getDangerousPermissions'])
        ->name('permissions.dangerous');

    // Obtener permisos de un usuario
    Route::get('/users/{userId}', [PermissionController::class, 'getUserPermissions'])
        ->name('permissions.user');

    // Asignar permisos adicionales a un usuario
    Route::post('/users/{userId}/assign', [PermissionController::class, 'assignToUser'])
        ->name('permissions.assign');

    // Revocar permisos de un usuario
    Route::post('/users/{userId}/revoke', [PermissionController::class, 'revokeFromUser'])
        ->name('permissions.revoke');

    // Sincronizar permisos (reemplazar todos los directos)
    Route::post('/users/{userId}/sync', [PermissionController::class, 'syncUserPermissions'])
        ->name('permissions.sync');

    // Obtener sugerencias de permisos para un rol
    Route::get('/suggestions/{role}', [PermissionController::class, 'getSuggestedPermissions'])
        ->name('permissions.suggestions');
});

/* ============================================
   USUARIOS
   ============================================ */
Route::prefix('users')->middleware(['auth:sanctum'])->group(function () {
    // CRUD REST (index, store, show, update, destroy)
    Route::get('/', [UserController::class, 'index'])
        ->middleware('permission:users.index');

    Route::get('/{id}', [UserController::class, 'show'])
        ->middleware('permission:users.show');

    Route::post('/', [UserController::class, 'store'])
        ->middleware('permission:users.store');

    Route::match(['put', 'patch'], '/{id}', [UserController::class, 'update'])
        ->middleware('permission:users.update');

    Route::delete('/{id}', [UserController::class, 'destroy'])
        ->middleware('permission:users.destroy');

    // Rutas adicionales
    Route::post('{id}/restore', [UserController::class, 'restore'])
        ->middleware('permission:users.restore');

    Route::patch('{id}/toggle-active', [UserController::class, 'toggleActive'])
        ->middleware('permission:users.toggle-active');

    Route::patch('{id}/change-role', [UserController::class, 'changeRole'])
        ->middleware('permission:users.change-role');
});

/* ============================================
   CATEGORIAS
   ============================================ */
Route::prefix('categories')->middleware(['auth:sanctum'])->group(function () {

    Route::get('/', [CategoryController::class, 'index'])
        ->middleware('permission:categories.index');

    Route::get('/tree', [CategoryController::class, 'tree'])
        ->middleware('permission:categories.tree');

    Route::get('/{id}', [CategoryController::class, 'show'])
        ->middleware('permission:categories.show');

    Route::post('/', [CategoryController::class, 'store'])
        ->middleware('permission:categories.store');

    Route::match(['put', 'patch'], '/{id}', [CategoryController::class, 'update'])
        ->middleware('permission:categories.update');

    Route::delete('/{id}', [CategoryController::class, 'destroy'])
        ->middleware('permission:categories.destroy');
});

/* ============================================
   ALMACENES
   ============================================ */
Route::prefix('warehouses')->middleware(['auth:sanctum'])->group(function () {

    Route::get('/', [WarehouseController::class, 'index'])
        ->middleware('permission:warehouses.index');

    Route::post('/', [WarehouseController::class, 'store'])
        ->middleware('permission:warehouses.store');

    Route::get('/{id}', [WarehouseController::class, 'show'])
        ->middleware('permission:warehouses.show');

    Route::match(['put', 'patch'], '/{id}', [WarehouseController::class, 'update'])
        ->middleware('permission:warehouses.update');

    Route::delete('/{id}', [WarehouseController::class, 'destroy'])
        ->middleware('permission:warehouses.destroy');

    Route::get('/{warehouse}/inventory', [InventoryController::class, 'getByWarehouse'])
        ->middleware('permission:warehouses.inventory');

    Route::get('/{warehouse}/inventory/statistics', [InventoryController::class, 'warehouseStatistics'])
        ->middleware('permission:warehouses.inventory.statistics');
});

/* ============================================
   PRODUCTOS
   ============================================ */
Route::middleware('auth:sanctum')->prefix('products')->group(function () {
    // Rutas especiales PRIMERO
    Route::post('bulk-update', [ProductController::class, 'bulkUpdate'])
        ->middleware('permission:products.bulk-update');

    Route::get('statistics', [ProductController::class, 'statistics'])
        ->middleware('permission:products.statistics');

    Route::post('{product}/duplicate', [ProductController::class, 'duplicate'])
        ->middleware('permission:products.duplicate');

    Route::post('{id}/restore', [ProductController::class, 'restore'])
        ->middleware('permission:products.restore');

    Route::post('{product}/images', [ProductController::class, 'uploadImages'])
        ->middleware('permission:products.images.upload');

    Route::delete('{product}/images/{mediaId}', [ProductController::class, 'deleteImage'])
        ->middleware('permission:products.images.delete');

    // Establecer imagen principal
    Route::patch('{product}/images/{mediaId}/set-primary', [ProductController::class, 'setPrimaryImage'])
        ->middleware('permission:products.images.set-primary');

    // Reordenar imágenes
    Route::patch('{product}/images/reorder', [ProductController::class, 'reorderImages'])
        ->middleware('permission:products.images.reorder');

    Route::get('{product}/inventory', [InventoryController::class, 'getByProduct'])
        ->middleware('permission:products.inventory');

    Route::get('{product}/inventory/statistics', [InventoryController::class, 'productStatistics'])
        ->middleware('permission:products.inventory.statistics');

    // CRUD básico
    Route::get('/', [ProductController::class, 'index'])
        ->middleware('permission:products.index');

    Route::post('/', [ProductController::class, 'store'])
        ->middleware('permission:products.store');

    Route::get('{product}', [ProductController::class, 'show'])
        ->middleware('permission:products.show');

    Route::match(['put', 'patch'], '{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update');

    Route::delete('{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.destroy');

    Route::prefix('{product}/attributes')->group(function () {

        Route::get('/', [ProductAttributeController::class, 'index'])
            ->middleware('permission:attributes.index');

        Route::post('/', [ProductAttributeController::class, 'store'])
            ->middleware('permission:attributes.store');

        Route::put('bulk', [ProductAttributeController::class, 'bulkUpdate'])
            ->middleware('permission:attributes.bulk-update');

        Route::put('{attribute}', [ProductAttributeController::class, 'update'])
            ->middleware('permission:attributes.update');

        Route::delete('{attribute}', [ProductAttributeController::class, 'destroy'])
            ->middleware('permission:attributes.destroy');
    });
});

/* ============================================
   INVENTARIO
   ============================================ */
Route::middleware('auth:sanctum')->prefix('inventory')->group(function () {
    // Estáticas/“palabras” primero
    Route::get('statistics/global', [InventoryController::class, 'globalStatistics'])
        ->middleware('permission:inventory.statistics.global');

    Route::get('alerts/low-stock', [InventoryController::class, 'lowStockAlert'])
        ->middleware('permission:inventory.alerts.low-stock');

    Route::get('alerts/out-of-stock', [InventoryController::class, 'outOfStockAlert'])
        ->middleware('permission:inventory.alerts.out-of-stock');


    // Listado y alta
    Route::get('/', [InventoryController::class, 'index'])
        ->middleware('permission:inventory.index');

    Route::post('/', [InventoryController::class, 'store'])
        ->middleware('permission:inventory.store');

    Route::post('bulk-assign', [InventoryController::class, 'bulkAssign'])
        ->middleware('permission:inventory.bulk-assign');

    // Dinámicas al final + constraints numéricos
    Route::get('{product}/{warehouse}', [InventoryController::class, 'show'])
        ->whereNumber('product')->whereNumber('warehouse')
        ->middleware('permission:inventory.show');

    Route::match(['put', 'patch'], '{product}/{warehouse}', [InventoryController::class, 'update'])
        ->whereNumber('product')->whereNumber('warehouse')
        ->middleware('permission:inventory.update');

    Route::delete('{product}/{warehouse}', [InventoryController::class, 'destroy'])
        ->whereNumber('product')->whereNumber('warehouse')
        ->middleware('permission:inventory.destroy');
});

/* ============================================
   ENTIDADES
   ============================================ */
Route::middleware('auth:sanctum')->prefix('entities')->group(function () {

    Route::get('search', [EntityController::class, 'search'])
        ->middleware('permission:entities.search');

    Route::get('find-by-document', [EntityController::class, 'findByDocument'])
        ->middleware('permission:entities.find-by-document');

    Route::patch('{entity}/deactivate', [EntityController::class, 'deactivate'])
        ->middleware('permission:entities.deactivate');

    Route::patch('{entity}/activate', [EntityController::class, 'activate'])
        ->middleware('permission:entities.activate');

    Route::get('/', [EntityController::class, 'index'])
        ->middleware('permission:entities.index');

    Route::post('/', [EntityController::class, 'store'])
        ->middleware('permission:entities.store');

    Route::get('{entity}', [EntityController::class, 'show'])
        ->middleware('permission:entities.show');

    Route::match(['put', 'patch'], '{entity}', [EntityController::class, 'update'])
        ->middleware('permission:entities.update');

    Route::delete('{entity}', [EntityController::class, 'destroy'])
        ->middleware('permission:entities.destroy');

    Route::prefix('{entity}/addresses')->group(function () {

        Route::get('/', [AddressController::class, 'index'])
            ->middleware('permission:addresses.index');

        Route::post('/', [AddressController::class, 'store'])
            ->middleware('permission:addresses.store');
    });
});

/* ============================================
   GESTIÓN DE STOCK (MANUAL)
   ============================================ */
Route::middleware('auth:sanctum')->prefix('stock')->group(function () {

    // Traslado entre almacenes
    Route::post('transfer', [StockManagementController::class, 'transfer'])
        ->middleware('permission:stock.transfer');

    // Ajustes de inventario
    Route::post('adjustment/in', [StockManagementController::class, 'adjustmentIn'])
        ->middleware('permission:stock.adjustment.in');

    Route::post('adjustment/out', [StockManagementController::class, 'adjustmentOut'])
        ->middleware('permission:stock.adjustment.out');

    // Consultas
    Route::get('batches', [StockManagementController::class, 'availableBatches'])
        ->middleware('permission:stock.batches');

    Route::get('movements/product/{productId}', [StockManagementController::class, 'productMovements'])
        ->middleware('permission:stock.movements');

    // Sincronización
    Route::post('sync', [StockManagementController::class, 'syncInventory'])
        ->middleware('permission:stock.sync');
});

/* ============================================
   DIRECCIONES
   ============================================ */
Route::middleware('auth:sanctum')->prefix('addresses')->group(function () {
    Route::patch('{address}/set-default', [AddressController::class, 'setDefault'])
        ->middleware('permission:addresses.set-default');

    Route::get('{address}', [AddressController::class, 'show'])
        ->middleware('permission:addresses.show');

    Route::match(['put', 'patch'], '{address}', [AddressController::class, 'update'])
        ->middleware('permission:addresses.update');

    Route::delete('{address}', [AddressController::class, 'destroy'])
        ->middleware('permission:addresses.destroy');
});

/* ============================================
   VALIDACIÓN SUNAT/RENIEC
   ============================================ */
Route::middleware('auth:sanctum')->prefix('sunat')->group(function () {
    Route::get('validate/{tipo}/{numero}', [SunatController::class, 'validateDocument'])
        ->middleware('throttle:10,1')
        ->middleware('permission:sunat.validate-document');
});

/* ============================================
   GEMINI AI
   ============================================ */
Route::middleware('auth:sanctum')->prefix('gemini')->group(function () {
    Route::post('/generate-product-info', [GeminiController::class, 'generateProductInfo'])
        ->middleware('permission:gemini.generate-product-info');

    Route::post('/generate-batch', [GeminiController::class, 'generateBatch'])
        ->middleware('permission:gemini.generate-batch');

    Route::post('/warm-cache', [GeminiController::class, 'warmCache'])
        ->middleware('permission:gemini.warm-cache');

    Route::post('/clear-cache', [GeminiController::class, 'clearCache'])
        ->middleware('permission:gemini.clear-cache');
});

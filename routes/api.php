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

/* CATEGORIAS */
Route::prefix('categories')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/tree', [CategoryController::class, 'tree']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::match(['put', 'patch'], '/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

/* ALMACENES */
Route::prefix('warehouses')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::post('/', [WarehouseController::class, 'store']);
    Route::get('/{id}', [WarehouseController::class, 'show']);
    Route::put('/{id}', [WarehouseController::class, 'update']);
    Route::patch('/{id}', [WarehouseController::class, 'update']);
    Route::delete('/{id}', [WarehouseController::class, 'destroy']);

    // NUEVO: Inventario de un almacén específico
    Route::get('/{warehouse}/inventory', [InventoryController::class, 'getByWarehouse']);
    Route::get('/{warehouse}/inventory/statistics', [InventoryController::class, 'warehouseStatistics']);
});

/* PRODUCTOS */
Route::middleware('auth:sanctum')->prefix('products')->group(function () {
    // Rutas especiales PRIMERO
    Route::post('bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::get('statistics', [ProductController::class, 'statistics']);
    Route::post('{product}/duplicate', [ProductController::class, 'duplicate']);
    Route::post('{id}/restore', [ProductController::class, 'restore']);

    // Gestión de imágenes
    Route::post('{product}/images', [ProductController::class, 'uploadImages']);
    Route::delete('{product}/images/{mediaId}', [ProductController::class, 'deleteImage']);

    // NUEVO: Inventario del producto
    Route::get('{product}/inventory', [InventoryController::class, 'getByProduct']);
    Route::get('{product}/inventory/statistics', [InventoryController::class, 'productStatistics']);

    // CRUD básico
    Route::get('/', [ProductController::class, 'index']);
    Route::post('/', [ProductController::class, 'store']);
    Route::get('{product}', [ProductController::class, 'show']);
    Route::match(['put', 'patch'], '{product}', [ProductController::class, 'update']);
    Route::delete('{product}', [ProductController::class, 'destroy']);

    Route::prefix('{product}/attributes')->group(function () {
        Route::get('/', [ProductAttributeController::class, 'index']);
        Route::post('/', [ProductAttributeController::class, 'store']);
        Route::put('bulk', [ProductAttributeController::class, 'bulkUpdate']);
        Route::put('{attribute}', [ProductAttributeController::class, 'update']);
        Route::delete('{attribute}', [ProductAttributeController::class, 'destroy']);
    });
});

/* ============================================
   INVENTARIO
   ============================================ */
Route::middleware('auth:sanctum')->prefix('inventory')->group(function () {
    // Estáticas/“palabras” primero
    Route::get('statistics/global', [InventoryController::class, 'globalStatistics']);
    Route::get('alerts/low-stock', [InventoryController::class, 'lowStockAlert']);
    Route::get('alerts/out-of-stock', [InventoryController::class, 'outOfStockAlert']);

    // Listado y alta
    Route::get('/', [InventoryController::class, 'index']);
    Route::post('/', [InventoryController::class, 'store']);
    Route::post('bulk-assign', [InventoryController::class, 'bulkAssign']);

    // Dinámicas al final + constraints numéricos
    Route::get('{product}/{warehouse}', [InventoryController::class, 'show'])
        ->whereNumber('product')->whereNumber('warehouse');

    Route::match(['put', 'patch'], '{product}/{warehouse}', [InventoryController::class, 'update'])
        ->whereNumber('product')->whereNumber('warehouse');

    Route::delete('{product}/{warehouse}', [InventoryController::class, 'destroy'])
        ->whereNumber('product')->whereNumber('warehouse');
});

/* ENTIDADES (CUSTOMERS & SUPPLIERS) */
Route::middleware('auth:sanctum')->prefix('entities')->group(function () {
    Route::get('search', [EntityController::class, 'search']);
    Route::get('find-by-document', [EntityController::class, 'findByDocument']);
    Route::patch('{entity}/deactivate', [EntityController::class, 'deactivate']);
    Route::patch('{entity}/activate', [EntityController::class, 'activate']);

    Route::get('/', [EntityController::class, 'index']);
    Route::post('/', [EntityController::class, 'store']);
    Route::get('{entity}', [EntityController::class, 'show']);
    Route::match(['put', 'patch'], '{entity}', [EntityController::class, 'update']);
    Route::delete('{entity}', [EntityController::class, 'destroy']);

    Route::prefix('{entity}/addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
    });
});

/* ============================================
   GESTIÓN DE STOCK (MANUAL)
   ============================================ */
Route::middleware('auth:sanctum')->prefix('stock')->group(function () {

    // Traslado entre almacenes
    Route::post('transfer', [StockManagementController::class, 'transfer']);

    // Ajustes de inventario
    Route::post('adjustment/in', [StockManagementController::class, 'adjustmentIn']);
    Route::post('adjustment/out', [StockManagementController::class, 'adjustmentOut']);

    // Consultas
    Route::get('batches', [StockManagementController::class, 'availableBatches']);
    Route::get('movements/product/{productId}', [StockManagementController::class, 'productMovements']);

    // Sincronización
    Route::post('sync', [StockManagementController::class, 'syncInventory']);
});

/* DIRECCIONES */
Route::middleware('auth:sanctum')->prefix('addresses')->group(function () {
    Route::patch('{address}/set-default', [AddressController::class, 'setDefault']);
    Route::get('{address}', [AddressController::class, 'show']);
    Route::match(['put', 'patch'], '{address}', [AddressController::class, 'update']);
    Route::delete('{address}', [AddressController::class, 'destroy']);
});

/* VALIDACIÓN SUNAT/RENIEC */
Route::middleware('auth:sanctum')->prefix('sunat')->group(function () {
    Route::get('validate/{tipo}/{numero}', [SunatController::class, 'validateDocument'])
        ->middleware('throttle:10,1');
});

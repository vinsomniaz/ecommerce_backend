<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*  PRODUCTOS   */

Route::prefix('products')->middleware(['auth:sanctum'])->group(function () {
    // CRUD básico
    Route::post('/', [ProductController::class, 'store']);

});

/* CATEGORIAS */
Route::prefix('categories')->middleware(['auth:sanctum'])->group(function() {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/tree', [CategoryController::class, 'tree']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::match(['put', 'patch'], '/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

/* ALMACENES */
Route::prefix('warehouses')->middleware(['auth:sanctum'])->group(function() {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::post('/', [WarehouseController::class, 'store']);
    Route::get('/{id}', [WarehouseController::class, 'show']);
    Route::put('/{id}', [WarehouseController::class, 'update']);
    Route::patch('/{id}', [WarehouseController::class, 'update']);
    Route::delete('/{id}', [WarehouseController::class, 'destroy']);
});

<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\SunatController;
use App\Http\Controllers\Api\AddressController;


/*  PRODUCTOS   */

Route::prefix('products')->middleware(['auth:sanctum'])->group(function () {
    // CRUD básico
    Route::post('/', [ProductController::class, 'store']);
});

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
Route::prefix('warehouses')->middleware(['auth:sanctum'])->group(function() {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::post('/', [WarehouseController::class, 'store']);
    Route::get('/{id}', [WarehouseController::class, 'show']);
    Route::put('/{id}', [WarehouseController::class, 'update']);
    Route::patch('/{id}', [WarehouseController::class, 'update']);
    Route::delete('/{id}', [WarehouseController::class, 'destroy']);
});

/* PRODUCTOS */
Route::middleware('auth:sanctum')->prefix('products')->group(function () {
    // Rutas especiales PRIMERO
    Route::post('bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::get('statistics', [ProductController::class, 'statistics']);
    Route::post('{product}/duplicate', [ProductController::class, 'duplicate']);
    Route::post('{id}/restore', [ProductController::class, 'restore']);

    // NUEVO: Gestión de imágenes
    Route::post('{product}/images', [ProductController::class, 'uploadImages']);
    Route::delete('{product}/images/{mediaId}', [ProductController::class, 'deleteImage']);

    // CRUD básico
    Route::get('/', [ProductController::class, 'index']);
    Route::post('/', [ProductController::class, 'store']);
    Route::get('{product}', [ProductController::class, 'show']);
    Route::match(['put', 'patch'], '{product}', [ProductController::class, 'update']);
    Route::delete('{product}', [ProductController::class, 'destroy']);
});



// Custom routes (before resource routes to avoid conflicts)
Route::get('/entities/search', [EntityController::class, 'search']);
Route::get('/entities/find-by-document', [EntityController::class, 'findByDocument']);
Route::patch('/entities/{entity}/deactivate', [EntityController::class, 'deactivate']);
Route::patch('/entities/{entity}/activate', [EntityController::class, 'activate']);

// Resource routes
Route::apiResource('entities', EntityController::class);

// Ruta de validación SUNAT/RENIEC
Route::get('/sunat/validate/{tipo}/{numero}', [SunatController::class, 'validateDocument'])
    ->middleware('throttle:10,1'); // Límite: 10 peticiones por minuto

Route::prefix('/entities/{entity}/addresses')->group(function () {
    Route::get('/', [AddressController::class, 'index']);
    Route::post('/', [AddressController::class, 'store']);
});

Route::prefix('/addresses/{address}')->group(function () {
    Route::get('/', [AddressController::class, 'show']);
    Route::put('/', [AddressController::class, 'update']);
    Route::delete('/', [AddressController::class, 'destroy']);
    Route::patch('/set-default', [AddressController::class, 'setDefault']);
});

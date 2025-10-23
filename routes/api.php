<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
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

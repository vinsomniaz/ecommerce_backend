<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EntityController;


/*  PRODUCTOS   */
Route::prefix('products')->middleware(['auth:sanctum'])->group(function () {
    // CRUD básico
    Route::post('/', [ProductController::class, 'store']);

});



// Custom routes (before resource routes to avoid conflicts)
Route::get('/entities/search', [EntityController::class, 'search']);
Route::get('/entities/find-by-document', [EntityController::class, 'findByDocument']);
Route::patch('/entities/{entity}/deactivate', [EntityController::class, 'deactivate']);
Route::patch('/entities/{entity}/activate', [EntityController::class, 'activate']);

// Resource routes
Route::apiResource('entities', EntityController::class);

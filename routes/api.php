<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EntityController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});



// Custom routes (before resource routes to avoid conflicts)
Route::get('/entities/search', [EntityController::class, 'search']);
Route::get('/entities/find-by-document', [EntityController::class, 'findByDocument']);
Route::patch('/entities/{entity}/deactivate', [EntityController::class, 'deactivate']);
Route::patch('/entities/{entity}/activate', [EntityController::class, 'activate']);

// Resource routes
Route::apiResource('entities', EntityController::class);

<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*  PRODUCTOS   */
Route::prefix('products')->middleware(['auth:sanctum'])->group(function () {
    // CRUD básico
    Route::post('/', [ProductController::class, 'store']);

});

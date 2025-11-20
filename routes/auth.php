<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ========================================
// RUTAS PÚBLICAS (GUEST)
// ========================================

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.store');

// ========================================
// RUTAS AUTENTICADAS USUARIO ECOMMERCE
// ========================================

Route::middleware('auth:sanctum')->group(function () {

    // Obtener usuario autenticado
    Route::get('/user', [AuthenticatedSessionController::class, 'me'])
        ->name('user.me');

    // Logout
    Route::delete('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Verificación de email
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// ========================================
// RUTAS AUTENTICADAS USUARIO ECOMMERCE
// ========================================

Route::middleware('auth:sanctum')->group(function () {

    // Obtener usuario autenticado
    Route::get('/user', [AuthenticatedSessionController::class, 'me'])
        ->name('user.me');

    // Logout
    Route::delete('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Verificación de email
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

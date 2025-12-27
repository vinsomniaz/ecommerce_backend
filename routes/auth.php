<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ========================================
// RUTAS PÚBLICAS (GUEST)
// ========================================

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/register-customer', [RegisteredUserController::class, 'storeCustomer'])
    ->middleware('guest')
    ->name('register.customer');

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
// RUTAS AUTENTICADAS
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

    // ========================================
    // PROFILE (Solo usuarios ERP: super-admin, admin, vendor)
    // ========================================

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    });
});

<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable) {
            // 1. URL del Frontend
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

            // 2. Generamos la URL firmada de la API solo para sacar los parámetros (expires, signature)
            $verifyUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // 3. Extraemos los parámetros de consulta (?expires=...&signature=...)
            $queryParams = parse_url($verifyUrl, PHP_URL_QUERY);

            // 4. Retornamos la URL FINAL que irá en el correo (Apuntando al Front)
            return "{$frontendUrl}/verify-email/{$notifiable->getKey()}/" . sha1($notifiable->getEmailForVerification()) . "?{$queryParams}";
        });

        // Configuración para Reset Password
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

            // El link que le llegará al usuario:
            // http://localhost:3000/reset-password?token=xyz&email=usuario@test.com
            return "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}

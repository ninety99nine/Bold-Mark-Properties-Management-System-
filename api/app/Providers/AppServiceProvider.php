<?php

namespace App\Providers;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

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
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            return $frontendUrl . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            $resetUrl = $frontendUrl . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return new ResetPasswordNotification($token, $resetUrl);
        });
    }
}

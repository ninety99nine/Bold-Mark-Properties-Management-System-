<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — BoldMark PMS
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api (via bootstrap/app.php).
| Version prefix /v1 is applied here.
|
| Resource route files live in routes/api/ and are loaded automatically
| via the glob() loop below. Each file registers its own prefix, middleware,
| and controller binding.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Health check — public
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => '1.0']))->name('health');

    // Resend webhook — public (no auth, Resend POSTs here for delivery/open tracking)
    Route::post('/webhooks/resend', [\App\Http\Controllers\Api\V1\ResendWebhookController::class, 'handle'])->name('webhooks.resend');

    // Public — occupant branding (resolved by subdomain, no auth required)
    Route::get('/branding', [\App\Http\Controllers\Api\V1\BrandingController::class, 'show'])->name('branding');

    // Auth routes (unauthenticated)
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'resetPassword'])->name('reset-password');
        Route::post('/2fa/challenge', [\App\Http\Controllers\Api\V1\Auth\TwoFactorController::class, 'challenge'])->name('2fa.challenge');

        // Forced 2FA enrollment during login (gated by the encrypted setup
        // challenge, not a bearer token) — for users who have not set up 2FA yet.
        Route::post('/2fa/enroll/start',   [\App\Http\Controllers\Api\V1\Auth\TwoFactorController::class, 'enrollStart'])->name('2fa.enroll.start');
        Route::post('/2fa/enroll/confirm', [\App\Http\Controllers\Api\V1\Auth\TwoFactorController::class, 'enrollConfirm'])->name('2fa.enroll.confirm');
    });

    // Authenticated — me, logout, 2FA management, sessions
    Route::middleware('auth:api')->prefix('auth')->name('auth.')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me'])->name('me');
        Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout'])->name('logout');

        // 2FA is mandatory and can never be disabled by a user (BUG-003), so no
        // disable route is exposed. setup/confirm remain for re-keying an
        // authenticator from an already-authenticated session.
        Route::prefix('2fa')->name('2fa.')->group(function () {
            Route::post('/setup',   [\App\Http\Controllers\Api\V1\Auth\TwoFactorController::class, 'setup'])->name('setup');
            Route::post('/confirm', [\App\Http\Controllers\Api\V1\Auth\TwoFactorController::class, 'confirm'])->name('confirm');
        });
    });

    // Active sessions
    Route::middleware('auth:api')->prefix('sessions')->name('sessions.')->group(function () {
        Route::get('/',         [\App\Http\Controllers\Api\V1\SessionController::class, 'index'])->name('index');
        Route::delete('/other', [\App\Http\Controllers\Api\V1\SessionController::class, 'destroyAll'])->name('destroy.all');
        Route::delete('/{session}', [\App\Http\Controllers\Api\V1\SessionController::class, 'destroy'])->name('destroy');
    });

    // Resource route files — each file in routes/api/ registers its own
    // prefix, middleware, and controller. Files are loaded alphabetically.
    foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
        require $routeFile;
    }

});

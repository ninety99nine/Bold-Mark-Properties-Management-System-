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

    // Public — tenant branding (resolved by subdomain, no auth required)
    Route::get('/branding', [\App\Http\Controllers\Api\V1\BrandingController::class, 'show'])->name('branding');

    // Auth routes (unauthenticated)
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // Authenticated — me & logout
    Route::middleware('auth:api')->prefix('auth')->name('auth.')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me'])->name('me');
        Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout'])->name('logout');
    });

    // Resource route files — each file in routes/api/ registers its own
    // prefix, middleware, and controller. Files are loaded alphabetically.
    foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
        require $routeFile;
    }

});

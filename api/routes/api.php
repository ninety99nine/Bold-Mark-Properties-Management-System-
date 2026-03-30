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
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Health check
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => '1.0']))->name('health');

    // Auth routes (unauthenticated)
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // Authenticated routes
    Route::middleware('auth:api')->group(function () {

        // Auth — user & logout
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me'])->name('me');
            Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout'])->name('logout');
        });

        // User management
        Route::apiResource('users', \App\Http\Controllers\Api\V1\UserController::class);

        // Communities
        Route::apiResource('communities', \App\Http\Controllers\Api\V1\CommunityController::class);

        // Units
        Route::apiResource('communities.units', \App\Http\Controllers\Api\V1\UnitController::class)->shallow();

        // Owners
        Route::apiResource('owners', \App\Http\Controllers\Api\V1\OwnerController::class);

    });

});

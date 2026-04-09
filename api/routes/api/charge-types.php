<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ChargeTypeController;

Route::prefix('charge-types')
    ->controller(ChargeTypeController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showChargeTypes')->name('show.charge.types');
        Route::post('/', 'createChargeType')->name('create.charge.type');
        Route::delete('/', 'deleteChargeTypes')->name('delete.charge.types');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{chargeType}')->group(function () {
            Route::get('/', 'showChargeType')->name('show.charge.type');
            Route::put('/', 'updateChargeType')->name('update.charge.type');
            Route::delete('/', 'deleteChargeType')->name('delete.charge.type');
        });
    });

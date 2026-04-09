<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UnitChargeConfigController;

Route::prefix('estates/{estate}/units/{unit}/charge-configs')
    ->controller(UnitChargeConfigController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showUnitChargeConfigs')->name('show.unit.charge.configs');
        Route::post('/', 'createUnitChargeConfig')->name('create.unit.charge.config');
        Route::delete('/', 'deleteUnitChargeConfigs')->name('delete.unit.charge.configs');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{chargeConfig}')->group(function () {
            Route::get('/', 'showUnitChargeConfig')->name('show.unit.charge.config');
            Route::put('/', 'updateUnitChargeConfig')->name('update.unit.charge.config');
            Route::delete('/', 'deleteUnitChargeConfig')->name('delete.unit.charge.config');
        });
    });

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UnitLedgerConfigController;

Route::prefix('communities/{community}/units/{unit}/ledger-configs')
    ->controller(UnitLedgerConfigController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showUnitLedgerConfigs')->name('show.unit.ledger.configs');
        Route::post('/', 'createUnitLedgerConfig')->name('create.unit.ledger.config');
        Route::delete('/', 'deleteUnitLedgerConfigs')->name('delete.unit.ledger.configs');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{ledgerConfig}')->group(function () {
            Route::get('/', 'showUnitLedgerConfig')->name('show.unit.ledger.config');
            Route::put('/', 'updateUnitLedgerConfig')->name('update.unit.ledger.config');
            Route::delete('/', 'deleteUnitLedgerConfig')->name('delete.unit.ledger.config');
        });
    });

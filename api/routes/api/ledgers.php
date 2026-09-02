<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LedgerController;

Route::prefix('ledgers')
    ->controller(LedgerController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showLedgers')->name('show.charge.types');
        Route::get('/options', 'ledgerOptions')->name('show.ledger.options');
        Route::get('/next-code', 'nextCode')->name('show.ledger.next.code');
        Route::post('/', 'createLedger')->name('create.charge.type');
        Route::delete('/', 'deleteLedgers')->name('delete.charge.types');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{ledger}')->group(function () {
            Route::get('/', 'showLedger')->name('show.charge.type');
            Route::put('/', 'updateLedger')->name('update.charge.type');
            Route::delete('/', 'deleteLedger')->name('delete.charge.type');
        });
    });

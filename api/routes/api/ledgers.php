<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LedgerController;

Route::prefix('ledgers')
    ->controller(LedgerController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showLedgers')->name('show.ledgers');
        Route::get('/options', 'ledgerOptions')->name('show.ledger.options');
        Route::get('/next-code', 'nextCode')->name('show.ledger.next.code');
        Route::post('/', 'createLedger')->name('create.ledger');
        Route::delete('/', 'deleteLedgers')->name('delete.ledgers');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{ledger}')->group(function () {
            Route::get('/', 'showLedger')->name('show.ledger');
            Route::put('/', 'updateLedger')->name('update.ledger');
            Route::delete('/', 'deleteLedger')->name('delete.ledger');
        });
    });

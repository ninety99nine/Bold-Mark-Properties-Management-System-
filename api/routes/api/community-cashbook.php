<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CashbookEntryController;

Route::prefix('communities/{community}/cashbook')
    ->controller(CashbookEntryController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/transactions', 'showCashbookTransactions')->name('show.community.cashbook.transactions');
        Route::post('/manual-transactions', 'createManualTransactions')->name('create.community.cashbook.manual.transactions');

        Route::get('/status', 'cashbookStatus')->name('show.community.cashbook.status');
        Route::get('/ledger-options', 'ledgerOptions')->name('show.community.cashbook.ledger.options');
        Route::get('/customer-search', 'customerSearch')->name('show.community.cashbook.customer.search');
        Route::post('/run-rules', 'runRules')->name('run.community.cashbook.rules');
        Route::get('/split-template-file', 'downloadSplitTemplate')->name('download.community.cashbook.split.template');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('entries/{cashbookEntry}')->group(function () {
            Route::post('/allocate', 'allocateEntry')->name('allocate.community.cashbook.entry');
            Route::delete('/allocation', 'deallocateEntry')->name('deallocate.community.cashbook.entry');
            Route::post('/split', 'splitEntry')->name('split.community.cashbook.entry');
            Route::post('/split/upload', 'uploadSplit')->name('upload.community.cashbook.entry.split');
        });
    });

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CashbookEntryController;

Route::prefix('cashbook')
    ->controller(CashbookEntryController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCashbookEntries')->name('show.cashbook.entries');
        Route::get('/export', 'exportCashbookEntries')->name('export.cashbook.entries');
        Route::get('/summary', 'showCashbookSummary')->name('show.cashbook.summary');
        Route::post('/', 'createCashbookEntry')->name('create.cashbook.entry');
        Route::post('/auto-allocate', 'autoAllocateCashbookEntries')->name('auto.allocate.cashbook.entries');
        Route::delete('/', 'deleteCashbookEntries')->name('delete.cashbook.entries');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{cashbookEntry}')->group(function () {
            Route::get('/', 'showCashbookEntry')->name('show.cashbook.entry');
            Route::put('/', 'updateCashbookEntry')->name('update.cashbook.entry');
            Route::post('/allocate', 'allocateCashbookEntry')->name('allocate.cashbook.entry');
            Route::post('/deallocate', 'deallocateCashbookEntry')->name('deallocate.cashbook.entry');
            Route::post('/proof-of-payment', 'uploadProofOfPayment')->name('upload.proof.of.payment');
            Route::get('/proof-of-payment/download', 'downloadProofOfPayment')->name('download.proof.of.payment');
            Route::delete('/proof-of-payment', 'deleteProofOfPayment')->name('delete.proof.of.payment');
            Route::delete('/', 'deleteCashbookEntry')->name('delete.cashbook.entry');
        });
    });

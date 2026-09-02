<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DetailedLedgerController;

Route::prefix('communities/{community}/detailed-ledger')
    ->controller(DetailedLedgerController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'run')->name('show.community.detailed.ledger');
        Route::get('/export', 'export')->name('export.community.detailed.ledger');
        Route::post('/email', 'emailReport')->name('email.community.detailed.ledger');
        Route::get('/reports', 'reports')->name('show.community.ledger.reports');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::get('/reports/{ledgerReport}/download', 'downloadReport')->name('download.community.ledger.report');
    });

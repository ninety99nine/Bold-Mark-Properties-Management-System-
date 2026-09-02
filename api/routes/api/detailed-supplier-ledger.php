<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DetailedSupplierLedgerController;

Route::prefix('communities/{community}/detailed-supplier-ledger')
    ->controller(DetailedSupplierLedgerController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'run')->name('show.community.detailed.supplier.ledger');
        Route::get('/export', 'export')->name('export.community.detailed.supplier.ledger');
    });

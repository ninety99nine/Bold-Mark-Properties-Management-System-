<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SupplierAgeAnalysisController;

Route::prefix('communities/{community}/supplier-age-analysis')
    ->controller(SupplierAgeAnalysisController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'getAgeAnalysis')->name('show.community.supplier.age.analysis');
        Route::get('/export', 'exportAgeAnalysis')->name('export.community.supplier.age.analysis');

        // Detailed-ledger drill-down for one supplier (static routes precede this).
        Route::get('/{supplier}', 'supplierLedger')->name('show.community.supplier.age.analysis.ledger');
    });

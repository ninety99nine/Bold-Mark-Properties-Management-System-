<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SupplierStatementController;

Route::prefix('communities/{community}/supplier-statements')
    ->controller(SupplierStatementController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'getStatements')->name('show.community.supplier.statements');
        Route::get('/view', 'viewPdf')->name('view.community.supplier.statements');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::get('/{supplier}/download', 'downloadStatement')->name('download.community.supplier.statement');
        Route::post('/{supplier}/email', 'emailStatement')->name('email.community.supplier.statement');
    });

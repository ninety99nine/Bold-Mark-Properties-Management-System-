<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CustomerStatementController;

Route::prefix('communities/{community}/customer-statements')
    ->controller(CustomerStatementController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'getStatements')->name('show.community.customer.statements');
        Route::get('/view', 'viewPdf')->name('view.community.customer.statements');
    });

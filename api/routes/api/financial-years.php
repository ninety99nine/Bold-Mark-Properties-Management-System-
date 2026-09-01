<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\FinancialYearController;

Route::prefix('communities/{community}/financial-years')
    ->controller(FinancialYearController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showFinancialYears')->name('show.community.financial.years');
    });

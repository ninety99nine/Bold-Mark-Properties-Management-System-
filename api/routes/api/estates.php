<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\EstateController;

Route::prefix('estates')
    ->controller(EstateController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showEstates')->name('show.estates');
        Route::get('/summary', 'showEstatesSummary')->name('show.estates.summary');
        Route::post('/', 'createEstate')->name('create.estate');
        Route::delete('/', 'deleteEstates')->name('delete.estates');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{estate}')->group(function () {
            Route::get('/', 'showEstate')->name('show.estate');
            Route::put('/', 'updateEstate')->name('update.estate');
            Route::delete('/', 'deleteEstate')->name('delete.estate');
            Route::get('/tenant-analytics', 'tenantAnalytics')->name('estate.tenant.analytics');
        });
    });

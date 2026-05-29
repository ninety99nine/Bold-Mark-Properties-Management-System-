<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\OrganizationController;

Route::prefix('organization')
    ->controller(OrganizationController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCurrentTenant')->name('show.organization');
        Route::put('/', 'updateCurrentTenant')->name('update.organization');
        Route::delete('/flush', 'flushCurrentTenant')->name('flush.organization');
        Route::get('/flush/{jobId}/status', 'flushStatus')->name('flush.organization.status');
    });

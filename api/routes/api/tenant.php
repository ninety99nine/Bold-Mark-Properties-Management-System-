<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\TenantController;

Route::prefix('tenant')
    ->controller(TenantController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCurrentTenant')->name('show.tenant');
        Route::put('/', 'updateCurrentTenant')->name('update.tenant');
    });

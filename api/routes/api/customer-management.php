<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CustomerManagementController;

Route::prefix('customer-management')
    ->controller(CustomerManagementController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'index')->name('show.customers');
    });

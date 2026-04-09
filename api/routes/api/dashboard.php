<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DashboardController;

Route::prefix('dashboard')
    ->controller(DashboardController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'getDashboardSummary')->name('show.dashboard');
    });

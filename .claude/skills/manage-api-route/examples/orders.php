<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

Route::prefix('customers/{customer}/orders')
    ->controller(OrderController::class)
    ->middleware(['auth:sanctum', 'account.access'])
    ->group(function () {
        Route::get('/', 'showOrders')->name('show.orders');
        Route::get('/summary', 'showOrdersSummary')->name('show.orders.summary');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{order}')->group(function () {
            Route::get('/', 'showOrder')->name('show.order');
        });
    });

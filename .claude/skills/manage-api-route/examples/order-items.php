<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderItemController;

Route::prefix('customers/{customer}/order-items')
    ->controller(OrderItemController::class)
    ->middleware(['auth:sanctum', 'account.access'])
    ->group(function () {
        Route::get('/', 'showOrderItems')->name('show.order.items');
        Route::post('/', 'createOrderItem')->name('create.order.item');
        Route::delete('/', 'deleteOrderItems')->name('delete.order.items');
        Route::get('/summary', 'showOrderItemsSummary')->name('show.order.items.summary');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{order_item}')->group(function () {
            Route::get('/', 'showOrderItem')->name('show.order.item');
            Route::delete('/', 'deleteOrderItem')->name('delete.order.item');
            Route::post('/fulfill', 'fulfillOrderItem')->name('fulfill.order.item');
            Route::post('/cancel', 'cancelOrderItem')->name('cancel.order.item');
            Route::get('/summary', 'showOrderItemSummary')->name('show.order.item.summary');
        });
    });

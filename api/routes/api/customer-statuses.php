<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CustomerStatusController;

Route::prefix('communities/{community}/customer-statuses')
    ->controller(CustomerStatusController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCustomerStatuses')->name('show.customer.statuses');
        Route::post('/apply', 'applyStatuses')->name('apply.customer.statuses');
        Route::get('/download', 'downloadStatuses')->name('download.customer.statuses');
        Route::get('/history', 'showStatusHistory')->name('show.customer.status.history');
        Route::get('/automatic-changes', 'showAutomaticStatusChanges')->name('show.customer.status.automatic');
    });

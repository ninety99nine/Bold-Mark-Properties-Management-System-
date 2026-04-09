<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;

Route::prefix('customers/{customer}/invoices')
    ->controller(InvoiceController::class)
    ->middleware(['auth:sanctum', 'account.access'])
    ->group(function () {
        Route::get('/', 'showInvoices')->name('show.invoices');
        Route::post('/', 'createInvoice')->name('create.invoice');
        Route::delete('/', 'deleteInvoices')->name('delete.invoices');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{invoice}')->group(function () {
            Route::get('/', 'showInvoice')->name('show.invoice');
            Route::put('/', 'updateInvoice')->name('update.invoice');
            Route::delete('/', 'deleteInvoice')->name('delete.invoice');
        });
    });

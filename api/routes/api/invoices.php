<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\InvoiceController;

Route::prefix('invoices')
    ->controller(InvoiceController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showInvoices')->name('show.invoices');
        Route::get('/export', 'exportInvoices')->name('export.invoices');
        Route::get('/summary', 'showInvoicesSummary')->name('show.invoices.summary');
        Route::get('/deleted', 'showDeletedInvoices')->name('show.deleted.invoices');
        Route::post('/', 'createInvoice')->name('create.invoice');
        Route::post('/run-billing', 'runBilling')->name('run.billing');
        Route::post('/adhoc-billing', 'createAdhocBilling')->name('create.adhoc.billing');
        Route::delete('/', 'deleteInvoices')->name('delete.invoices');

        // Active invoice routes — {invoice} resolves via standard model binding
        Route::prefix('{invoice}')->group(function () {
            Route::get('/', 'showInvoice')->name('show.invoice');
            Route::put('/', 'updateInvoice')->name('update.invoice');
            Route::post('/resend', 'resendInvoice')->name('resend.invoice');
            Route::get('/download-pdf', 'downloadPdf')->name('download.invoice.pdf');
            Route::delete('/', 'deleteInvoice')->name('delete.invoice');
        });

        // Trash routes — {deletedInvoice} resolves via withTrashed binding (AppServiceProvider)
        Route::prefix('{deletedInvoice}')->group(function () {
            Route::post('/restore', 'restoreInvoice')->name('restore.invoice');
            Route::delete('/force-delete', 'forceDeleteInvoice')->name('force.delete.invoice');
        });
    });

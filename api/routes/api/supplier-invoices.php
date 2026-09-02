<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SupplierInvoiceController;

Route::prefix('communities/{community}/supplier-invoices')
    ->controller(SupplierInvoiceController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showSupplierInvoices')->name('show.community.supplier.invoices');
        Route::post('/', 'createSupplierInvoice')->name('create.community.supplier.invoice');

        // Explicit route model binding applied: AppServiceProvider.php ({supplierInvoice}).
        Route::prefix('{supplierInvoice}')->group(function () {
            Route::get('/', 'showSupplierInvoice')->name('show.community.supplier.invoice');
            Route::put('/', 'updateSupplierInvoice')->name('update.community.supplier.invoice');
            Route::get('/pdf', 'downloadPdf')->name('download.community.supplier.invoice.pdf');
            Route::delete('/', 'deleteSupplierInvoice')->name('delete.community.supplier.invoice');
        });
    });

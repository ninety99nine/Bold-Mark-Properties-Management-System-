<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SupplierController;

Route::prefix('suppliers')
    ->controller(SupplierController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showSuppliers')->name('show.suppliers');
        Route::post('/', 'createSupplier')->name('create.supplier');
        Route::delete('/', 'deleteSuppliers')->name('delete.suppliers');

        // Static routes must precede the {supplier} wildcard.
        Route::get('/options', 'supplierOptions')->name('show.supplier.options');
        Route::get('/export', 'exportSuppliers')->name('export.suppliers');
        Route::get('/upload-template', 'downloadUploadTemplate')->name('download.supplier.template');
        Route::post('/import', 'importSuppliers')->name('import.suppliers');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{supplier}')->group(function () {
            Route::get('/', 'showSupplier')->name('show.supplier');
            Route::put('/', 'updateSupplier')->name('update.supplier');
            Route::delete('/', 'deleteSupplier')->name('delete.supplier');

            Route::get('/documents', 'showDocuments')->name('show.supplier.documents');
            Route::post('/documents', 'uploadDocument')->name('upload.supplier.document');
            Route::delete('/documents/{supplierDocument}', 'deleteDocument')->name('delete.supplier.document');
        });
    });

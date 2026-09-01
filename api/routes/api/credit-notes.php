<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CreditNoteController;

Route::prefix('credit-notes')
    ->controller(CreditNoteController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCreditNotes')->name('show.credit.notes');
        Route::get('/creditable-invoices', 'showCreditableInvoices')->name('show.creditable.invoices');
        Route::post('/', 'createCreditNote')->name('create.credit.note');

        Route::prefix('{creditNote}')->group(function () {
            Route::get('/', 'showCreditNote')->name('show.credit.note');
            Route::get('/download-pdf', 'downloadPdf')->name('download.credit.note.pdf');
            Route::delete('/', 'deleteCreditNote')->name('delete.credit.note');
        });
    });

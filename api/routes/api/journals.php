<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\JournalController;

Route::prefix('journals')
    ->controller(JournalController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showJournalBatches')->name('show.journal.batches');
        Route::get('/template', 'downloadTemplate')->name('download.journal.template');
        Route::get('/download', 'downloadBatchesExcel')->name('download.journal.batches.excel');
        Route::post('/', 'createJournalBatch')->name('create.journal.batch');
        Route::post('/upload', 'uploadJournalBatch')->name('upload.journal.batch');

        Route::prefix('{journalBatch}')->group(function () {
            Route::get('/', 'showJournalBatch')->name('show.journal.batch');
            Route::get('/download', 'downloadBatch')->name('download.journal.batch');
            Route::put('/', 'updateJournalBatch')->name('update.journal.batch');
            Route::delete('/', 'deleteJournalBatch')->name('delete.journal.batch');
        });
    });

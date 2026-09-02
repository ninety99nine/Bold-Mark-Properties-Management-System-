<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitPqController;

Route::prefix('communities/{community}/units')
    ->controller(UnitController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showUnits')->name('show.units');
        Route::get('/export', 'exportUnits')->name('export.units');
        Route::get('/occupants/export', 'exportOccupants')->name('export.occupants');
        Route::post('/', 'createUnit')->name('create.unit');
        Route::delete('/', 'deleteUnits')->name('delete.units');

        // Bulk import — defined before {unit} prefix to prevent parameter conflicts
        Route::get('/bulk-import/template', 'downloadImportTemplate')->name('bulk.import.template');
        Route::post('/bulk-import/parse', 'parseImportFile')->name('bulk.import.parse');
        Route::post('/bulk-import', 'bulkImportUnits')->name('bulk.import.units');

        // Occupants import — defined before {unit} prefix to prevent parameter conflicts
        Route::get('/occupants-import/template', 'downloadOccupantsTemplate')->name('occupants.import.template');
        Route::post('/occupants-import/parse', 'parseOccupantsImportFile')->name('occupants.import.parse');
        Route::post('/occupants-import', 'importOccupants')->name('occupants.import');

        // PQ (Participation Quota) batch export/import
        Route::get('/pq/export', [UnitPqController::class, 'export'])->name('pq.export');
        Route::post('/pq/parse', [UnitPqController::class, 'parse'])->name('pq.parse');
        Route::post('/pq/import', [UnitPqController::class, 'import'])->name('pq.import');
        Route::delete('/pq', [UnitPqController::class, 'clearAll'])->name('pq.clear');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{unit}')->group(function () {
            Route::get('/', 'showUnit')->name('show.unit');
            Route::put('/', 'updateUnit')->name('update.unit');
            Route::delete('/', 'deleteUnit')->name('delete.unit');
            Route::post('/toggle-development', 'toggleDevelopment')->name('toggle.development');
            Route::get('/activities', 'showUnitActivities')->name('show.unit.activities');

            // Multiple owners (WeConnectU "Add Owner")
            Route::post('/owners', 'addOwner')->name('add.unit.owner');
            Route::put('/owners/{owner}', 'updateOwnerRecord')->name('update.unit.owner');
            Route::delete('/owners/{owner}', 'deleteOwner')->name('delete.unit.owner');

            // Collection notes (finances Notes log)
            Route::get('/collection-notes', 'showCollectionNotes')->name('show.collection.notes');
            Route::post('/collection-notes', 'addCollectionNote')->name('add.collection.note');
            Route::get('/statement', 'downloadStatement')->name('download.statement');
            Route::post('/statement/email', 'emailStatement')->name('email.statement');
            Route::get('/customer-ledger', 'showCustomerLedger')->name('show.customer.ledger');

            // Communication (e-mail) log
            Route::get('/communications', 'showCommunications')->name('show.communications');
            Route::post('/communications', 'sendCommunication')->name('send.communication');
            Route::post('/communications/{communication}/resend', 'resendCommunication')->name('resend.communication');
            Route::get('/communications/{communication}/download', 'downloadCommunication')->name('download.communication');

            // Offences (conduct-rule violations)
            Route::get('/offences', 'showOffences')->name('show.offences');
            Route::post('/offences', 'createOffence')->name('create.offence');
            Route::put('/offences/{offence}', 'updateOffence')->name('update.offence');
            Route::delete('/offences/{offence}', 'deleteOffence')->name('delete.offence');

            // Tasks (maintenance / action items)
            Route::get('/tasks', 'showTasks')->name('show.tasks');
            Route::post('/tasks', 'createTask')->name('create.task');
            Route::put('/tasks/{task}', 'updateTask')->name('update.task');
            Route::delete('/tasks/{task}', 'deleteTask')->name('delete.task');
            Route::post('/tasks/{task}/updates', 'addTaskUpdate')->name('add.task.update');

            // Documents (customer documents)
            Route::get('/documents', 'showDocuments')->name('show.documents');
            Route::post('/documents', 'uploadDocument')->name('upload.document');
            Route::delete('/documents/{document}', 'deleteDocument')->name('delete.document');
        });
    });

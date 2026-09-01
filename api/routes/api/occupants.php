<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\OccupantController;

Route::prefix('communities/{community}/units/{unit}/occupants')
    ->controller(OccupantController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showOccupants')->name('show.unit.organizations');
        Route::post('/', 'createOccupant')->name("create.occupant");
        Route::delete('/', 'deleteOccupants')->name('delete.unit.organizations');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{occupant}')->group(function () {
            Route::get('/', 'showOccupant')->name("show.occupant");
            Route::put('/', 'updateOccupant')->name("update.occupant");
            Route::post('/move-out', 'moveOutOccupant')->name("move.out.occupant");
            Route::post('/reinstate', 'reinstateOccupant')->name("reinstate.occupant");
            Route::delete('/', 'deleteOccupant')->name("delete.occupant");
            Route::post('/lease-document', 'uploadLeaseDocument')->name('upload.lease.document');
            Route::delete('/lease-document', 'deleteLeaseDocument')->name('delete.lease.document');
        });
    });

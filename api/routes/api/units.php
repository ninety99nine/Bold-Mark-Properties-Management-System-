<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UnitController;

Route::prefix('estates/{estate}/units')
    ->controller(UnitController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showUnits')->name('show.units');
        Route::get('/export', 'exportUnits')->name('export.units');
        Route::post('/', 'createUnit')->name('create.unit');
        Route::delete('/', 'deleteUnits')->name('delete.units');

        // Bulk import — defined before {unit} prefix to prevent parameter conflicts
        Route::get('/bulk-import/template', 'downloadImportTemplate')->name('bulk.import.template');
        Route::post('/bulk-import/parse', 'parseImportFile')->name('bulk.import.parse');
        Route::post('/bulk-import', 'bulkImportUnits')->name('bulk.import.units');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{unit}')->group(function () {
            Route::get('/', 'showUnit')->name('show.unit');
            Route::put('/', 'updateUnit')->name('update.unit');
            Route::delete('/', 'deleteUnit')->name('delete.unit');
            Route::get('/activities', 'showUnitActivities')->name('show.unit.activities');
        });
    });

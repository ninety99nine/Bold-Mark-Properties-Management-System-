<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitPqController;

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
            Route::get('/activities', 'showUnitActivities')->name('show.unit.activities');
        });
    });

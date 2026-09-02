<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AllocationRuleController;

Route::prefix('communities/{community}/allocation-rules')
    ->controller(AllocationRuleController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showAllocationRules')->name('show.allocation.rules');
        Route::post('/', 'createAllocationRule')->name('create.allocation.rule');

        Route::prefix('{allocationRule}')->group(function () {
            Route::get('/', 'showAllocationRule')->name('show.allocation.rule');
            Route::put('/', 'updateAllocationRule')->name('update.allocation.rule');
            Route::delete('/', 'deleteAllocationRule')->name('delete.allocation.rule');
        });
    });

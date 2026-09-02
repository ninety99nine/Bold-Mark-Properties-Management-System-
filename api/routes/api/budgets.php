<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BudgetController;

Route::prefix('communities/{community}/budgets')
    ->controller(BudgetController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showBudgets')->name('show.community.budgets');
        Route::put('/', 'upsertBudgets')->name('upsert.community.budgets');

        Route::post('/lock', 'lockBudget')->name('lock.community.budget');
        Route::post('/unlock', 'unlockBudget')->name('unlock.community.budget');

        Route::get('/template', 'downloadTemplate')->name('download.community.budget.template');
        Route::post('/import', 'importBudget')->name('import.community.budget');
    });

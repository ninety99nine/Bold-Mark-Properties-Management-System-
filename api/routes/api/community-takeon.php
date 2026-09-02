<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CommunityTakeOnController;

// ── Community Take-on (WeConnectU parity) ──────────────────────────────────
Route::prefix('communities/{community}')
    ->controller(CommunityTakeOnController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        // Owner Sheet (customer template) upload
        Route::get('/owner-sheet/template', 'downloadOwnerSheetTemplate')->name('community.owner.sheet.template');
        Route::post('/owner-sheet/parse', 'parseOwnerSheet')->name('community.owner.sheet.parse');
        Route::post('/owner-sheet/import', 'importOwnerSheet')->name('community.owner.sheet.import');

        // Budget upload
        Route::get('/budget/template', 'downloadBudgetTemplate')->name('community.budget.template');
        Route::post('/budget/parse', 'parseBudget')->name('community.budget.parse');
        Route::post('/budget/import', 'importBudget')->name('community.budget.import');

        // Take-on step history (append-only log of uploads + N/A markers) + file actions
        Route::get('/take-on/status', 'takeOnStatus')->name('community.takeon.status');
        Route::post('/take-on/{key}/not-applicable', 'markNotApplicable')->name('community.takeon.not.applicable');
        Route::get('/take-on/items/{item}/file', 'downloadTakeOnFile')->name('community.takeon.file');

        // Submit take-on (Take-on → Active)
        Route::post('/submit-take-on', 'submitTakeOn')->name('community.submit.takeon');
    });

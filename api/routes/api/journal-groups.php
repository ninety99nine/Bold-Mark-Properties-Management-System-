<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\JournalGroupController;

Route::prefix('communities/{community}/journal-groups')
    ->controller(JournalGroupController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showJournalGroups')->name('show.journal.groups');
        Route::post('/', 'createJournalGroup')->name('create.journal.group');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{journalGroup}')->group(function () {
            Route::get('/', 'showJournalGroup')->name('show.journal.group');
            Route::put('/', 'updateJournalGroup')->name('update.journal.group');
            Route::delete('/', 'deleteJournalGroup')->name('delete.journal.group');
        });
    });

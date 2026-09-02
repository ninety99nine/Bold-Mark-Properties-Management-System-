<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SplitTemplateController;

Route::prefix('communities/{community}/split-templates')
    ->controller(SplitTemplateController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showSplitTemplates')->name('show.split.templates');
        Route::post('/', 'createSplitTemplate')->name('create.split.template');

        Route::prefix('{splitTemplate}')->group(function () {
            Route::get('/', 'showSplitTemplate')->name('show.split.template');
            Route::delete('/', 'deleteSplitTemplate')->name('delete.split.template');
        });
    });

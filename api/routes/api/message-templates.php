<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MessageTemplateController;

Route::prefix('message-templates')
    ->controller(MessageTemplateController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showMessageTemplates')->name('show.message.templates');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{messageTemplate}')->group(function () {
            Route::put('/', 'updateMessageTemplate')->name('update.message.template');
            Route::post('/reset', 'resetMessageTemplate')->name('reset.message.template');
        });
    });

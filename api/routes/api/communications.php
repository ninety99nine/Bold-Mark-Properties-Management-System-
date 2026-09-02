<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CommunicationController;

Route::prefix('communities/{community}/communications')
    ->controller(CommunicationController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCommunications')->name('show.community.communications');
        Route::post('/', 'sendCommunication')->name('send.community.communication');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{communicationLog}')->group(function () {
            Route::post('/resend', 'resendCommunication')->name('resend.community.communication');
        });
    });

Route::prefix('communities/{community}/email-settings')
    ->controller(CommunicationController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showEmailSettings')->name('show.community.email.settings');
        Route::post('/', 'updateEmailSettings')->name('update.community.email.settings');
    });

Route::prefix('communications')
    ->controller(CommunicationController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showAllCommunications')->name('show.all.communications');
    });

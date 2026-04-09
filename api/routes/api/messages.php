<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MessageController;

Route::prefix('messages')
    ->controller(MessageController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::post('/send', 'send')->name('messages.send');
    });

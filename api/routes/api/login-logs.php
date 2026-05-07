<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LoginLogController;

Route::prefix('login-logs')
    ->controller(LoginLogController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showAllLoginLogs')->name('show.login.logs');
    });

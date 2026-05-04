<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ArrearsController;

Route::prefix('arrears')
    ->controller(ArrearsController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'index')->name('show.arrears');
    });

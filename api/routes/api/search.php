<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SearchController;

Route::prefix('search')
    ->controller(SearchController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'search')->name('global.search');
    });

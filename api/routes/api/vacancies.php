<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\VacancyController;

Route::prefix('vacancies')
    ->controller(VacancyController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'index')->name('show.vacancies');
    });

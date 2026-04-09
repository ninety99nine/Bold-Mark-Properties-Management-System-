<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AgeAnalysisController;

Route::prefix('age-analysis')
    ->controller(AgeAnalysisController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'getAgeAnalysis')->name('show.age.analysis');
        Route::get('/export', 'exportAgeAnalysis')->name('export.age.analysis');
    });

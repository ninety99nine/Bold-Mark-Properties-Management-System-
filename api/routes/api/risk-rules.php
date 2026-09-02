<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\RiskRuleController;

Route::prefix('risk-rules')
    ->controller(RiskRuleController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/',         'showRiskRules')->name('show.risk-rules');
        Route::post('/',        'createRiskRule')->name('create.risk-rule');
        Route::get('/evaluate', 'evaluate')->name('evaluate.risk-rules');
        Route::put('/reorder',  'reorder')->name('reorder.risk-rules');

        Route::prefix('{riskRule}')->group(function () {
            Route::put('/',    'updateRiskRule')->name('update.risk-rule');
            Route::delete('/', 'deleteRiskRule')->name('delete.risk-rule');
        });
    });

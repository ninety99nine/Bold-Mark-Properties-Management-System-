<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\GlReportController;

Route::prefix('communities/{community}/reports')
    ->controller(GlReportController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/trial-balance', 'trialBalance')->name('show.community.report.trial.balance');
        Route::get('/trial-balance/export', 'trialBalanceExport')->name('export.community.report.trial.balance');

        Route::get('/general-ledger', 'generalLedger')->name('show.community.report.general.ledger');
        Route::get('/general-ledger/export', 'generalLedgerExport')->name('export.community.report.general.ledger');

        Route::get('/income-statement', 'incomeStatement')->name('show.community.report.income.statement');
        Route::get('/income-statement/export', 'incomeStatementExport')->name('export.community.report.income.statement');

        Route::get('/actual-vs-budget', 'actualVsBudget')->name('show.community.report.actual.vs.budget');
        Route::get('/actual-vs-budget/export', 'actualVsBudgetExport')->name('export.community.report.actual.vs.budget');

        Route::get('/vat-201', 'vat201')->name('show.community.report.vat.201');
        Route::get('/vat-201/export', 'vat201Export')->name('export.community.report.vat.201');

        Route::get('/cash-movement', 'cashMovement')->name('show.community.report.cash.movement');
        Route::get('/cash-movement/export', 'cashMovementExport')->name('export.community.report.cash.movement');
    });

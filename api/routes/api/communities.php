<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CommunityController;
use App\Http\Controllers\Api\V1\CommunityTaskController;
use App\Http\Controllers\Api\V1\CommunityBillingSetupController;

Route::prefix('communities')
    ->controller(CommunityController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCommunities')->name('show.communities');
        Route::get('/summary', 'showCommunitiesSummary')->name('show.communities.summary');
        Route::post('/', 'createCommunity')->name('create.community');
        Route::delete('/', 'deleteCommunities')->name('delete.communities');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{community}')->group(function () {
            Route::get('/', 'showCommunity')->name('show.community');
            Route::get('/dashboard', 'dashboard')->name('community.dashboard');
            Route::put('/', 'updateCommunity')->name('update.community');
            Route::delete('/', 'deleteCommunity')->name('delete.community');
            Route::get('/occupant-analytics', 'occupantAnalytics')->name('community.occupant.analytics');

            // Default Billing Setup (charge → ledger mappings + toggles)
            Route::get('/billing-setup', [CommunityBillingSetupController::class, 'showBillingSetup'])->name('community.billing.setup.show');
            Route::put('/billing-setup', [CommunityBillingSetupController::class, 'updateBillingSetup'])->name('community.billing.setup.update');
        });
    });

// ── Community-level Tasks (WeConnectU parity) ──────────────────────────────
Route::prefix('communities/{community}/tasks')
    ->controller(CommunityTaskController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/',              'index')->name('community.tasks.index');
        Route::post('/',             'store')->name('community.tasks.store');
        Route::get('/{task}',        'show')->name('community.tasks.show');
        Route::put('/{task}',        'update')->name('community.tasks.update');
        Route::delete('/{task}',     'destroy')->name('community.tasks.destroy');
        Route::post('/{task}/updates', 'addUpdate')->name('community.tasks.updates');
    });

// ── Community Settings → Users (WeConnectU parity) ─────────────────────────
Route::prefix('communities/{community}/users')
    ->controller(\App\Http\Controllers\Api\V1\CommunityUserController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/',                       'index')->name('community.users.index');
        Route::post('/',                      'store')->name('community.users.store');
        Route::put('/directors-trustees',     'updateDirectorsTrustees')->name('community.users.directors');
        Route::put('/payment-authorisations', 'updatePaymentAuthorisations')->name('community.users.authorisations');
        Route::put('/{member}',               'update')->name('community.users.update');
        Route::delete('/{member}',            'destroy')->name('community.users.destroy');
        Route::post('/{member}/reset-password', 'resetPassword')->name('community.users.reset.password');
    });

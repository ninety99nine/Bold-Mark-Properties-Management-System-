<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\TenantController;

Route::prefix('estates/{estate}/units/{unit}/tenants')
    ->controller(TenantController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showTenants')->name('show.unit.tenants');
        Route::post('/', 'createTenant')->name("create.tenant");
        Route::delete('/', 'deleteTenants')->name('delete.unit.tenants');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{tenant}')->group(function () {
            Route::get('/', 'showTenant')->name("show.tenant");
            Route::put('/', 'updateTenant')->name("update.tenant");
            Route::post('/move-out', 'moveOutTenant')->name("move.out.tenant");
            Route::post('/reinstate', 'reinstateTenant')->name("reinstate.tenant");
            Route::delete('/', 'deleteTenant')->name("delete.tenant");
            Route::post('/lease-document', 'uploadLeaseDocument')->name('upload.lease.document');
            Route::delete('/lease-document', 'deleteLeaseDocument')->name('delete.lease.document');
        });
    });

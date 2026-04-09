<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UnitTenantController;

Route::prefix('estates/{estate}/units/{unit}/tenants')
    ->controller(UnitTenantController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showUnitTenants')->name('show.unit.tenants');
        Route::post('/', 'createUnitTenant')->name('create.unit.tenant');
        Route::delete('/', 'deleteUnitTenants')->name('delete.unit.tenants');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{unitTenant}')->group(function () {
            Route::get('/', 'showUnitTenant')->name('show.unit.tenant');
            Route::put('/', 'updateUnitTenant')->name('update.unit.tenant');
            Route::post('/move-out', 'moveOutUnitTenant')->name('move.out.unit.tenant');
            Route::post('/reinstate', 'reinstateUnitTenant')->name('reinstate.unit.tenant');
            Route::delete('/', 'deleteUnitTenant')->name('delete.unit.tenant');
            Route::post('/lease-document', 'uploadLeaseDocument')->name('upload.lease.document');
            Route::delete('/lease-document', 'deleteLeaseDocument')->name('delete.lease.document');
        });
    });

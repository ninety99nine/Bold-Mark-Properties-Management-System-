<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SupplierGroupController;

Route::prefix('supplier-groups')
    ->controller(SupplierGroupController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showSupplierGroups')->name('show.supplier.groups');
        Route::post('/', 'createSupplierGroup')->name('create.supplier.group');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{supplierGroup}')->group(function () {
            Route::get('/', 'showSupplierGroup')->name('show.supplier.group');
            Route::delete('/', 'deleteSupplierGroup')->name('delete.supplier.group');
        });
    });

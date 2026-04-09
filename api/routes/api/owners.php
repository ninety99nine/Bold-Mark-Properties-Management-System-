<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\OwnerController;

Route::prefix('owners')
    ->controller(OwnerController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showOwners')->name('show.owners');
        Route::delete('/', 'deleteOwners')->name('delete.owners');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{owner}')->group(function () {
            Route::get('/', 'showOwner')->name('show.owner');
            Route::put('/', 'updateOwner')->name('update.owner');
            Route::delete('/', 'deleteOwner')->name('delete.owner');
        });
    });

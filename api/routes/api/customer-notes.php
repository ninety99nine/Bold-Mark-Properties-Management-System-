<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CustomerNoteController;

Route::prefix('communities/{community}/units/{unit}/notes')
    ->controller(CustomerNoteController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'index')->name('show.customer.notes');
        Route::post('/', 'store')->name('store.customer.note');
        Route::post('/phonecall', 'phonecall')->name('store.customer.phonecall');
        Route::put('/{note}', 'update')->name('update.customer.note');
        Route::delete('/{note}', 'destroy')->name('destroy.customer.note');
    });

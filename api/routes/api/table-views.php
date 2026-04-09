<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\TableViewController;

/*
|--------------------------------------------------------------------------
| Table Views — Saved user-defined filter/sort/date-range configurations
|--------------------------------------------------------------------------
|
| Views are scoped per user per context (e.g. 'units', 'invoices').
| A view stores a named combination of date range, filters, and sort that
| can be recalled on any data table in the platform.
|
| GET    /table-views?context=units  → list all views for the user in that context
| POST   /table-views                → create a new view
| PUT    /table-views/{tableView}    → update an existing view
| DELETE /table-views/{tableView}    → delete a view
|
*/

Route::prefix('table-views')
    ->controller(TableViewController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'index')->name('index.table-views');
        Route::post('/', 'store')->name('create.table-view');
        Route::put('/{tableView}', 'update')->name('update.table-view');
        Route::delete('/{tableView}', 'destroy')->name('delete.table-view');
    });

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CustomerController;

Route::prefix('communities/{community}/customers')
    ->controller(CustomerController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCustomers')->name('show.community.customers');
        Route::post('/', 'createCustomer')->name('create.community.customer');

        // Bulk tools (declared before the {owner} binding group so the literal segments match first)
        Route::get('/notes-template', 'downloadNotesTemplate')->name('download.customer.notes.template');
        Route::post('/notes-import', 'importNotes')->name('import.customer.notes');
        Route::get('/mandates-template', 'downloadMandatesTemplate')->name('download.debit.order.mandates.template');
        Route::post('/mandates-import', 'importMandates')->name('import.debit.order.mandates');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{owner}')->group(function () {
            Route::get('/', 'showCustomer')->name('show.customer');
            Route::put('/', 'updateCustomer')->name('update.customer');
            Route::post('/disable', 'disableCustomer')->name('disable.customer');
            Route::post('/enable', 'enableCustomer')->name('enable.customer');
            Route::delete('/', 'deleteCustomer')->name('delete.customer');
        });
    });

Route::prefix('communities/{community}/customer-groups')
    ->controller(CustomerController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showCustomerGroups')->name('show.customer.groups');
        Route::post('/', 'createCustomerGroup')->name('create.customer.group');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{customerGroup}')->group(function () {
            Route::put('/', 'updateCustomerGroup')->name('update.customer.group');
            Route::delete('/', 'deleteCustomerGroup')->name('delete.customer.group');
        });
    });

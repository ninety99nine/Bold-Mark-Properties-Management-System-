<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BankAccountController;

Route::prefix('bank-accounts')
    ->controller(BankAccountController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showBankAccounts')->name('show.bank.accounts');
        Route::post('/', 'createBankAccount')->name('create.bank.account');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{bankAccount}')->group(function () {
            Route::get('/', 'showBankAccount')->name('show.bank.account');
            Route::put('/', 'updateBankAccount')->name('update.bank.account');
            Route::delete('/', 'deleteBankAccount')->name('delete.bank.account');
        });
    });

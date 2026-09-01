<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BankAccountController;

Route::prefix('bank-accounts')
    ->controller(BankAccountController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showBankAccounts')->name('show.bank.accounts');
    });

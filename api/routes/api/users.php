<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UserController;

Route::prefix('users')
    ->controller(UserController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'showUsers')->name('show.users');
        Route::get('/summary', 'showUsersSummary')->name('show.users.summary');
        Route::post('/', 'inviteUser')->name('invite.user');
        Route::delete('/', 'deleteUsers')->name('delete.users');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{user}')->group(function () {
            Route::get('/', 'showUser')->name('show.user');
            Route::put('/', 'updateUser')->name('update.user');
            Route::delete('/', 'deleteUser')->name('delete.user');
            Route::post('/send-password-reset', 'sendPasswordResetLink')->name('send.password.reset');
            Route::put('/estates', 'syncUserEstates')->name('sync.user.estates');
        });
    });

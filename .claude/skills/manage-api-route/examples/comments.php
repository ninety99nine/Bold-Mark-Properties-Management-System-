<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommentController;

Route::prefix('posts/{post}/comments')
    ->controller(CommentController::class)
    ->middleware(['auth:sanctum', 'workspace.member'])
    ->group(function () {
        Route::get('/', 'showComments')->name('show.comments');
        Route::post('/', 'createComment')->name('create.comment');
        Route::delete('/', 'deleteComments')->name('delete.comments');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{comment}')->group(function () {
            Route::get('/', 'showComment')->name('show.comment');
            Route::put('/', 'updateComment')->name('update.comment');
            Route::delete('/', 'deleteComment')->name('delete.comment');
        });
    });

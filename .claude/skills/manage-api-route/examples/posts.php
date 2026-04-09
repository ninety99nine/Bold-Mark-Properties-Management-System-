<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;

Route::prefix('posts')
    ->middleware(['auth:sanctum'])
    ->controller(PostController::class)
    ->group(function () {
        Route::get('/', 'showPosts')->name('show.posts');
        Route::post('/', 'createPost')->name('create.post');
        Route::delete('/', 'deletePosts')->name('delete.posts');

        // Explicit route model binding applied: AppServiceProvider.php
        Route::middleware(['workspace.member'])->prefix('{post}')->group(function () {
            Route::get('/', 'showPost')->middleware(['track.post.view'])->name('show.post');
            Route::put('/', 'updatePost')->middleware(['track.post.view'])->name('update.post');
            Route::post('/thumbnail', 'uploadPostThumbnail')->middleware(['track.post.view'])->name('upload.post.thumbnail');
            Route::delete('/', 'deletePost')->name('delete.post');
        });
    });

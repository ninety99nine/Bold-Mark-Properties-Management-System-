<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\NotificationController;

Route::prefix('notifications')
    ->controller(NotificationController::class)
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/', 'index')->name('show.notifications');
        Route::post('/mark-all-read', 'markAllAsRead')->name('mark.all.notifications.read');
        Route::post('/{notificationId}/mark-read', 'markAsRead')->name('mark.notification.read');
    });

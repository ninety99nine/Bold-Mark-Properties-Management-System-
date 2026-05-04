<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Return the authenticated user's notifications (latest 30).
     */
    public function index(): JsonResponse
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->take(30)
            ->get()
            ->map(fn ($n) => [
                'id'        => $n->id,
                'type'      => class_basename($n->type),
                'data'      => $n->data,
                'read_at'   => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        $unreadCount = Auth::user()->unreadNotifications()->count();

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $notificationId): JsonResponse
    {
        $notification = Auth::user()
            ->notifications()
            ->findOrFail($notificationId);

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read']);
    }
}

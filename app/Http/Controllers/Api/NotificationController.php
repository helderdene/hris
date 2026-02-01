<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->take(20)
            ->get();

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => NotificationResource::collection($notifications),
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $tenant, DatabaseNotification $notification): JsonResponse
    {
        // Ensure the notification belongs to the authenticated user
        if ($notification->notifiable_id !== $request->user()->id) {
            abort(403, 'Unauthorized to access this notification.');
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => new NotificationResource($notification),
        ]);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Download a file associated with a notification (e.g., bulk payslip PDF).
     */
    public function download(Request $request, string $tenant, DatabaseNotification $notification): mixed
    {
        // Ensure the notification belongs to the authenticated user
        if ($notification->notifiable_id !== $request->user()->id) {
            abort(403, 'Unauthorized to access this notification.');
        }

        $data = $notification->data;

        if (empty($data['file_path'])) {
            abort(404, 'No file associated with this notification.');
        }

        $filePath = $data['file_path'];
        $fileName = $data['file_name'] ?? basename($filePath);

        if (! Storage::disk('local')->exists($filePath)) {
            abort(404, 'File not found.');
        }

        // Mark notification as read when downloading
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return Storage::disk('local')->download($filePath, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}

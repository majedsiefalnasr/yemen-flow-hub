<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Notifications\DatabaseNotification;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(path: '/api/notifications', tags: ['Notifications'], summary: 'List current user notifications', responses: [new OA\Response(response: 200, description: 'Notifications retrieved')])]
    public function index()
    {
        $items = request()->user()->notifications()->latest()->paginate(20);

        return ApiResponse::success(NotificationResource::collection($items), 'Notifications retrieved.');
    }

    #[OA\Post(path: '/api/notifications/{notification}/read', tags: ['Notifications'], summary: 'Mark one notification as read', parameters: [new OA\Parameter(name: 'notification', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Notification marked read')])]
    public function read(DatabaseNotification $notification)
    {
        if ($notification->notifiable_id !== request()->user()->id || $notification->notifiable_type !== request()->user()::class) {
            return ApiResponse::forbidden();
        }

        $notification->markAsRead();

        return ApiResponse::success((object) [], 'Notification marked as read.');
    }

    #[OA\Get(path: '/api/notifications/unread-count', tags: ['Notifications'], summary: 'Get unread notification count for current user', responses: [new OA\Response(response: 200, description: 'Unread count retrieved')])]
    public function unreadCount()
    {
        $count = request()->user()->unreadNotifications()->count();

        return ApiResponse::success(['count' => $count], 'Unread count retrieved.');
    }

    #[OA\Post(path: '/api/notifications/read-all', tags: ['Notifications'], summary: 'Mark all current user notifications as read', responses: [new OA\Response(response: 200, description: 'All notifications marked read')])]
    public function readAll()
    {
        request()->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success((object) [], 'All notifications marked as read.');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Models\NotificationRecipient;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationInboxController extends Controller
{
    public function index(Request $request)
    {
        $query = NotificationRecipient::query()
            ->where('user_id', $request->user()->id)
            ->with('notification')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->has('status')) {
            match ($request->input('status')) {
                'unread' => $query->whereNull('read_at'),
                'read' => $query->whereNotNull('read_at'),
                'archived' => $query->whereNotNull('archived_at'),
                default => null,
            };
        } else {
            $query->whereNull('archived_at');
        }

        $items = $query->paginate(20);

        return ApiResponse::success([
            'data' => $items->getCollection()->map(fn (NotificationRecipient $r) => [
                'id' => $r->id,
                'notification_id' => $r->notification_id,
                'type' => $r->notification->type,
                'severity' => $r->notification->severity,
                'title' => $r->notification->title,
                'body' => $r->notification->body,
                'entity_type' => $r->notification->entity_type,
                'entity_id' => $r->notification->entity_id,
                'action_url' => $r->notification->action_url,
                'read_at' => $r->read_at?->toISOString(),
                'archived_at' => $r->archived_at?->toISOString(),
                'created_at' => $r->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Notifications retrieved.');
    }

    public function unreadCount(Request $request)
    {
        $count = NotificationRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->count();

        return ApiResponse::success(['count' => $count], 'Unread count retrieved.');
    }

    public function read(Request $request, int $id)
    {
        $recipient = $this->findOwnRecipient($request, $id);

        $recipient->update(['read_at' => now()]);

        return ApiResponse::success((object) [], 'Notification marked as read.');
    }

    public function unread(Request $request, int $id)
    {
        $recipient = $this->findOwnRecipient($request, $id);

        $recipient->update(['read_at' => null]);

        return ApiResponse::success((object) [], 'Notification marked as unread.');
    }

    public function archive(Request $request, int $id)
    {
        $recipient = $this->findOwnRecipient($request, $id);

        $recipient->update(['archived_at' => now()]);

        return ApiResponse::success((object) [], 'Notification archived.');
    }

    public function readAll(Request $request)
    {
        NotificationRecipient::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success((object) [], 'All notifications marked as read.');
    }

    private function findOwnRecipient(Request $request, int $id): NotificationRecipient
    {
        $recipient = NotificationRecipient::findOrFail($id);

        if ((int) $recipient->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only act on your own notifications.');
        }

        return $recipient;
    }
}

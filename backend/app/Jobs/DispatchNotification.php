<?php

namespace App\Jobs;

use App\Models\EngineNotification;
use App\Models\NotificationRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $type,
        private readonly string $severity,
        private readonly string $title,
        private readonly ?string $body,
        private readonly ?string $entityType,
        private readonly ?int $entityId,
        private readonly ?string $actionUrl,
        private readonly array $recipientUserIds,
    ) {}

    public function handle(): void
    {
        if (empty($this->recipientUserIds)) {
            return;
        }

        $notification = EngineNotification::create([
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'body' => $this->body,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'action_url' => $this->actionUrl,
        ]);

        $now = now();
        $rows = [];
        foreach (array_unique($this->recipientUserIds) as $userId) {
            $rows[] = [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // insertOrIgnore so a retry after a partial write cannot fail on the
        // unique(notification_id, user_id) constraint.
        NotificationRecipient::insertOrIgnore($rows);
    }
}

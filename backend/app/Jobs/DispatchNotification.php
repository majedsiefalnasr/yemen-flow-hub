<?php

namespace App\Jobs;

use App\Models\EngineNotification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceGate;
use App\Services\Operations\OperationalAlertLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class DispatchNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /** QUEUE-002: explicit retry bound instead of inheriting worker defaults. */
    public int $tries = 3;

    /** Fan-out to a bounded recipient list; should never legitimately run long. */
    public int $timeout = 30;

    public function __construct(
        private readonly string $type,
        private readonly string $severity,
        private readonly string $title,
        private readonly ?string $body,
        private readonly ?string $entityType,
        private readonly ?int $entityId,
        private readonly ?string $actionUrl,
        private readonly array $recipientUserIds,
    ) {
        // QUEUE-003: dedicated queue so notification fan-out doesn't compete
        // with scans/exports on `default`.
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (empty($this->recipientUserIds)) {
            return;
        }

        $gate = app(NotificationPreferenceGate::class);
        $recipientUserIds = User::query()
            ->whereIn('id', $this->recipientUserIds)
            ->get()
            ->filter(fn (User $user) => $gate->shouldDeliver($user, $this->type, $this->severity))
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        if (empty($recipientUserIds)) {
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
        foreach (array_unique($recipientUserIds) as $userId) {
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

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function failed(\Throwable $exception): void
    {
        OperationalAlertLogger::failure('notification_dispatch', $exception, [
            'type' => $this->type,
            'recipient_count' => count($this->recipientUserIds),
        ]);
    }
}

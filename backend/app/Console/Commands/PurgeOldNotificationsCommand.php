<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Models\EngineNotification;
use App\Models\NotificationRecipient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PurgeOldNotificationsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'notifications:purge-old';

    protected $description = 'Purge notification recipients past retention horizon';

    public function handle(): int
    {
        return $this->runWithHeartbeat(function (): int {
            $purged = 0;

            $purged += $this->purgeUnreadRecipients();
            $purged += $this->purgeReadOrArchivedRecipients();
            $purged += $this->purgeOrphanNotifications();

            return $purged;
        });
    }

    private function purgeUnreadRecipients(): int
    {
        $cutoff = now()->subDays(config('retention.notification_unread_max_days'));

        $ids = NotificationRecipient::query()
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->where('created_at', '<', $cutoff)
            ->whereHas('notification', fn ($q) => $this->applyRetentionExemption($q))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return NotificationRecipient::query()->whereIn('id', $ids)->delete();
    }

    private function purgeReadOrArchivedRecipients(): int
    {
        $cutoff = now()->subDays(config('retention.notification_read_days'));

        $ids = NotificationRecipient::query()
            ->where(function ($q) {
                $q->whereNotNull('read_at')->orWhereNotNull('archived_at');
            })
            ->whereRaw('COALESCE(archived_at, read_at) < ?', [$cutoff])
            ->whereHas('notification', fn ($q) => $this->applyRetentionExemption($q))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return NotificationRecipient::query()->whereIn('id', $ids)->delete();
    }

    private function purgeOrphanNotifications(): int
    {
        return EngineNotification::query()
            ->whereDoesntHave('recipients')
            ->delete();
    }

    /**
     * @param  Builder<EngineNotification>  $query
     * @return Builder<EngineNotification>
     */
    private function applyRetentionExemption($query)
    {
        return $query
            ->where('severity', '!=', 'critical')
            ->where('type', 'not like', 'security.%');
    }
}

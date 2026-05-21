<?php

namespace App\Notifications;

use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClaimReleasedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ImportRequest $requestModel,
        private readonly string $reason,
        private readonly ?User $releasedBy = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $reasonLabel = $this->reason === 'ttl_expired' ? 'انتهاء المهلة' : 'يدوي';

        return [
            'type' => 'claim_released',
            'message' => 'أُلغيت مطالبة على الطلب ' . $this->requestModel->reference_number . ' — ' . $reasonLabel,
            'request_id' => $this->requestModel->id,
            'reference_number' => $this->requestModel->reference_number,
            'released_by_user_id' => $this->releasedBy?->id,
            'released_by_name' => $this->releasedBy?->name,
            'reason' => $this->reason,
        ];
    }
}

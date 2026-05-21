<?php

namespace App\Notifications;

use App\Models\ImportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RequestRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ImportRequest $requestModel,
        private readonly bool $terminal = false,
        private readonly ?string $comment = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'request_rejected',
            'message' => 'تم رفض الطلب: ' . $this->requestModel->reference_number,
            'request_id' => $this->requestModel->id,
            'reference_number' => $this->requestModel->reference_number,
            'terminal' => $this->terminal,
            'comment' => $this->comment,
        ];
    }
}

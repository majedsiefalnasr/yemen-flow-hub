<?php

namespace App\Notifications;

use App\Models\ImportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RequestApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ImportRequest $requestModel)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'request_approved',
            'message' => 'تمت الموافقة على الطلب: ' . $this->requestModel->reference_number,
            'request_id' => $this->requestModel->id,
            'reference_number' => $this->requestModel->reference_number,
        ];
    }
}

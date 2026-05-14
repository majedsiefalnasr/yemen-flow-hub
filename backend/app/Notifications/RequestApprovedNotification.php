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
        return ['message' => 'Your request has been approved.', 'request_id' => $this->requestModel->id];
    }
}

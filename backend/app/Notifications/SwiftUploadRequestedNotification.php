<?php

namespace App\Notifications;

use App\Models\ImportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SwiftUploadRequestedNotification extends Notification implements ShouldQueue
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
        return ['message' => 'SWIFT upload is requested for this request.', 'request_id' => $this->requestModel->id];
    }
}

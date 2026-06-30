<?php

namespace App\Notifications;

use App\Models\ImportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SwiftUploadRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ImportRequest $requestModel) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'swift_upload_requested',
            'message' => 'مطلوب رفع وثيقة SWIFT للطلب: '.$this->requestModel->reference_number,
            'request_id' => $this->requestModel->id,
            'reference_number' => $this->requestModel->reference_number,
        ];
    }
}

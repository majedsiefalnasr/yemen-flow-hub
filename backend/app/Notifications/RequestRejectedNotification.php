<?php

namespace App\Notifications;

use App\Enums\RequestStatus;
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
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        // Use the request's real status; never default a null status to
        // EXECUTIVE_REJECTED, which would mislabel a bank/support rejection
        // (code-review 17-F). Fall back to a neutral label only if truly absent.
        $status = $this->requestModel->status;
        $statusLabel = $status instanceof RequestStatus ? $status->label() : 'غير متاح / Unavailable';

        return [
            'type' => 'request_rejected',
            'message' => 'حالة الطلب: '.$statusLabel.' — '.$this->requestModel->reference_number,
            'request_id' => $this->requestModel->id,
            'reference_number' => $this->requestModel->reference_number,
            'terminal' => $this->terminal,
            'comment' => $this->comment,
        ];
    }
}

<?php

namespace App\Mail;

use App\Enums\AuditAction;
use App\Models\ImportRequest;
use App\Services\Audit\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RequestRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public readonly ImportRequest $requestModel,
        public readonly bool $terminal = false,
        public readonly ?string $comment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم رفض طلبكم - Yemen Flow Hub'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.request-rejected'
        );
    }

    public function attachments(): array
    {
        return [];
    }

    public function failed(Throwable $exception): void
    {
        app(AuditService::class)->log(
            AuditAction::EMAIL_DELIVERY_FAILED,
            null,
            null,
            [
                'mailable' => static::class,
                'recipient' => $this->requestModel->creator?->email,
                'request_id' => $this->requestModel->id,
                'error' => $exception->getMessage(),
            ]
        );
    }
}

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

class VotingOpenedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public readonly ImportRequest $requestModel) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم فتح جلسة التصويت - Yemen Flow Hub'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.voting-opened'
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
                'recipient' => null,
                'request_id' => $this->requestModel->id,
                'error' => $exception->getMessage(),
            ]
        );
    }
}

<?php

namespace App\Mail;

use App\Enums\AuditAction;
use App\Models\ImportRequest;
use App\Services\Audit\AuditService;
use App\Services\Mail\EmailTemplateService;
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

    protected ?array $rendered = null;

    public function __construct(
        public readonly ImportRequest $requestModel,
        public readonly bool $terminal = false,
        public readonly ?string $comment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getRendered()['subject']
        );
    }

    public function content(): Content
    {
        $rendered = $this->getRendered();

        if ($rendered['source'] === 'db') {
            return new Content(htmlString: $rendered['body']);
        }

        return new Content(view: 'emails.request-rejected');
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

    private function getRendered(): array
    {
        if ($this->rendered === null) {
            $this->rendered = app(EmailTemplateService::class)->render('rejected', [
                'user_name' => $this->requestModel->creator?->name ?? '',
                'request_reference' => $this->requestModel->reference_number ?? '',
                'importer_name' => $this->requestModel->supplier_name ?? '',
                'amount' => (string) ($this->requestModel->amount ?? ''),
                'currency' => $this->requestModel->currency ?? '',
                'status' => $this->requestModel->current_status ?? '',
                'action_url' => '',
                'bank_name' => $this->requestModel->bank?->name ?? '',
                'requestModel' => $this->requestModel,
                'terminal' => $this->terminal,
                'comment' => $this->comment,
            ]);
        }

        return $this->rendered;
    }
}

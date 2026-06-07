<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Enums\EmailDeliveryStatus;
use App\Enums\NotificationType;
use App\Models\EmailDelivery;
use App\Services\Audit\AuditService;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\NotificationRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendEmailDelivery implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $deliveryId,
        public readonly ?string $renderedSubject = null,
        public readonly ?string $renderedBody = null,
    ) {
        $this->onConnection('emails');
        $this->onQueue('emails');
    }

    public function handle(EmailDeliveryService $deliveries, ?NotificationRegistry $registry = null): void
    {
        $registry ??= app(NotificationRegistry::class);
        $delivery = $this->findDeliveryOrFail();

        if ($delivery->status !== EmailDeliveryStatus::QUEUED) {
            return;
        }

        if ($this->isRedactedType($delivery, $registry) && ($this->isMissingLivePayload($this->renderedSubject) || $this->isMissingLivePayload($this->renderedBody))) {
            throw new RuntimeException('Redacted email delivery requires a live encrypted payload.');
        }

        // Atomic claim before transport: only one attempt may send. A retry after a
        // worker crash between transport and markSent finds the row already claimed
        // and stops, preventing a duplicate send (notably duplicate OTP/reset codes).
        if (! $deliveries->claimForSending($delivery)) {
            return;
        }

        $subject = $this->renderedSubject ?? (string) $delivery->rendered_subject;
        $body = $this->renderedBody ?? (string) $delivery->rendered_body;

        $sent = Mail::html($body, function ($message) use ($delivery, $subject): void {
            $message
                ->to($delivery->recipient_email)
                ->subject($subject);
        });

        $deliveries->markSent($delivery, $sent?->getMessageId());
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = $this->findDelivery();

        if ($delivery === null) {
            return;
        }

        $deliveries = app(EmailDeliveryService::class);
        $rawError = $exception?->getMessage() ?? 'Email delivery job failed.';

        // For redacted types, a transport error can echo the rendered body/subject
        // (incl. the live secret). Mask it before it reaches the failed-status column
        // and the audit log, which the redaction layer would otherwise bypass.
        $error = $this->isRedactedType($delivery, app(NotificationRegistry::class))
            ? $deliveries->redact($rawError)
            : $rawError;

        $deliveries->markFailed($delivery, $error);

        app(AuditService::class)->log(
            AuditAction::EMAIL_DELIVERY_FAILED,
            null,
            $delivery,
            [
                'notification_type' => $delivery->notification_type,
                'event_id' => $delivery->event_id,
                'recipient_user_id' => $delivery->recipient_user_id,
                'recipient_email' => $delivery->recipient_email,
                'error' => $error,
            ]
        );
    }

    private function findDeliveryOrFail(): EmailDelivery
    {
        return EmailDelivery::query()->findOrFail($this->deliveryId);
    }

    private function findDelivery(): ?EmailDelivery
    {
        return EmailDelivery::query()->find($this->deliveryId);
    }

    private function isRedactedType(EmailDelivery $delivery, NotificationRegistry $registry): bool
    {
        $type = NotificationType::tryFrom($delivery->notification_type);

        return $type !== null && $registry->for($type)['persist_body'] === 'redacted';
    }

    private function isMissingLivePayload(?string $value): bool
    {
        return $value === null || $value === '';
    }
}

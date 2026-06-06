<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Enums\EmailDeliveryStatus;
use App\Models\EmailDelivery;
use App\Services\Audit\AuditService;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public readonly int $deliveryId)
    {
        $this->onConnection('emails');
        $this->onQueue('emails');
    }

    public function handle(EmailDeliveryService $deliveries): void
    {
        $delivery = $this->findDeliveryOrFail();

        if ($delivery->status !== EmailDeliveryStatus::QUEUED) {
            return;
        }

        Mail::html((string) $delivery->rendered_body, function ($message) use ($delivery): void {
            $message
                ->to($delivery->recipient_email)
                ->subject((string) $delivery->rendered_subject);
        });

        $deliveries->markSent($delivery);
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = $this->findDelivery();

        if ($delivery === null) {
            return;
        }

        $error = $exception?->getMessage() ?? 'Email delivery job failed.';
        app(EmailDeliveryService::class)->markFailed($delivery, $error);

        app(AuditService::class)->log(
            AuditAction::EMAIL_DELIVERY_FAILED,
            null,
            $delivery,
            [
                'notification_type' => $delivery->notification_type,
                'event_id' => $delivery->event_id,
                'recipient_user_id' => $delivery->recipient_user_id,
                'recipient_email' => $delivery->recipient_email,
                'error' => $exception?->getMessage(),
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
}

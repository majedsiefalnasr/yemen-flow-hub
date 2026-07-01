<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Jobs\SendEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\User;
use App\Support\EmailEventId;
use Illuminate\Support\Facades\DB;

class SendEmailNotification
{
    public function __construct(
        private readonly NotificationRegistry $registry,
        private readonly EmailDeliveryService $deliveries,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * Security/auth emails are intentionally mail-only: no database notification
     * row is created, but the send still goes through the governed outbox path.
     *
     * @param  array<string, mixed>  $liveVariables
     * @param  array<string, mixed>  $maskedVariables
     */
    public function sendAuth(
        NotificationType $type,
        User $recipient,
        string $issuanceId,
        array $liveVariables,
        array $maskedVariables,
    ): void {
        $definition = $this->registry->for($type);

        if ($definition['channels'] !== ['mail'] || $definition['persist_body'] !== 'redacted') {
            throw new \InvalidArgumentException("Notification type [{$type->value}] is not a redacted mail-only auth type.");
        }

        if (empty($recipient->email)) {
            return;
        }

        $delivery = $this->deliveries->reserve(
            $type,
            EmailEventId::forAuth($type->value, $issuanceId),
            $recipient->id,
            $recipient->email,
            'mail'
        );

        if ($delivery === null) {
            return;
        }

        $live = $this->renderAuthSnapshotOrRelease($delivery, $type, $recipient, $liveVariables, $maskedVariables);
        if ($live === null) {
            return;
        }

        $this->queueDeliveryAfterCommit($delivery->id, $live['subject'], $live['html']);
    }

    /**
     * @param  array<string, mixed>  $liveVariables
     * @param  array<string, mixed>  $maskedVariables
     * @return array{subject: string, html: string}|null
     */
    private function renderAuthSnapshotOrRelease(
        EmailDelivery $delivery,
        NotificationType $type,
        User $recipient,
        array $liveVariables,
        array $maskedVariables,
    ): ?array {
        $base = ['user_name' => (string) $recipient->name];

        try {
            $live = $this->renderer->render($type, array_merge($base, $liveVariables));
            $masked = $this->renderer->render($type, array_merge($base, $maskedVariables));
            $this->deliveries->finalize(
                $delivery,
                $masked['subject'],
                $masked['html'],
                $masked['template_version_id']
            );

            return $live;
        } catch (\Throwable $e) {
            // Free the reservation on render/finalize failure so a re-issuance can
            // send; never leave the auth row stuck reserved-but-undelivered.
            $this->deliveries->release($delivery);
            report($e);

            return null;
        }
    }

    private function queueDeliveryAfterCommit(
        int $deliveryId,
        ?string $renderedSubject = null,
        ?string $renderedBody = null,
    ): void {
        DB::afterCommit(function () use ($deliveryId, $renderedSubject, $renderedBody): void {
            SendEmailDelivery::dispatch($deliveryId, $renderedSubject, $renderedBody);
        });
    }
}

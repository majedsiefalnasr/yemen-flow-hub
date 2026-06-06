<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Jobs\SendEmailDelivery;
use App\Models\ImportRequest;
use App\Models\User;
use App\Support\EmailEventId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Workflow email orchestrator (Story 15.4).
 *
 * Pipeline per recipient: registry gate → org-scoped recipients → reserve outbox row
 * → render template → finalize snapshot → queue SendEmailDelivery after commit.
 */
class SendEmailNotification
{
    private const MANDATORY_TYPES = [
        NotificationType::REQUEST_REJECTED,
        NotificationType::REQUEST_RETURNED,
        NotificationType::REQUEST_APPROVED,
    ];

    public function __construct(
        private readonly NotificationRegistry $registry,
        private readonly EmailDeliveryService $deliveries,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendWorkflow(NotificationType $type, ImportRequest $request, array $context = []): void
    {
        $definition = $this->registry->for($type);

        if (! in_array('mail', $definition['channels'], true)) {
            return;
        }

        if ($definition['recipient_roles'] === []) {
            return;
        }

        $eventId = EmailEventId::forWorkflow($request->id, $request->status->value);

        foreach ($this->resolveRecipients($request, $definition['recipient_roles']) as $recipient) {
            $this->deliverToRecipient($type, $eventId, $request, $recipient, $context);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function deliverToRecipient(
        NotificationType $type,
        string $eventId,
        ImportRequest $request,
        User $recipient,
        array $context,
    ): void {
        if (! $this->shouldEmailNotify($recipient, $type)) {
            return;
        }

        $delivery = $this->deliveries->reserve(
            $type,
            $eventId,
            $recipient->id,
            $recipient->email,
            'mail'
        );

        if ($delivery === null) {
            return;
        }

        $rendered = $this->renderer->render($type, $this->variablesFor($request, $recipient, $context));

        $this->deliveries->finalize(
            $delivery,
            $rendered['subject'],
            $rendered['html'],
            $rendered['template_version_id']
        );

        $this->queueDeliveryAfterCommit($delivery->id);
    }

    private function queueDeliveryAfterCommit(int $deliveryId): void
    {
        DB::afterCommit(function () use ($deliveryId): void {
            SendEmailDelivery::dispatch($deliveryId);
        });
    }

    /**
     * @param  array<int, UserRole>  $roles
     * @return Collection<int, User>
     */
    private function resolveRecipients(ImportRequest $request, array $roles): Collection
    {
        [$bankRoles, $cbyRoles] = $this->partitionRecipientRoles($roles);

        return User::query()
            ->whereNotNull('email')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($bankRoles, $cbyRoles, $request): void {
                if ($bankRoles !== []) {
                    $query->orWhere(function (Builder $bankQuery) use ($bankRoles, $request): void {
                        $bankQuery
                            ->where('bank_id', $request->bank_id)
                            ->whereIn('role', $bankRoles);
                    });
                }

                if ($cbyRoles !== []) {
                    $query->orWhereIn('role', $cbyRoles);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, UserRole>  $roles
     * @return array{0: list<string>, 1: list<string>}
     */
    private function partitionRecipientRoles(array $roles): array
    {
        $bankRoles = [];
        $cbyRoles = [];

        foreach ($roles as $role) {
            if ($role->isBankRole()) {
                $bankRoles[] = $role->value;
            } elseif ($role->isCbyRole()) {
                $cbyRoles[] = $role->value;
            }
        }

        return [$bankRoles, $cbyRoles];
    }

    private function shouldEmailNotify(User $user, NotificationType $type): bool
    {
        if (empty($user->email)) {
            return false;
        }

        if (! (bool) ($user->user_preferences['email_notifications'] ?? false)) {
            return false;
        }

        if (in_array($type, self::MANDATORY_TYPES, true)) {
            return true;
        }

        $prefs = $user->user_preferences['notification_preferences'] ?? [];

        return ($prefs[$this->preferenceKey($type)] ?? true) !== false;
    }

    private function preferenceKey(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::REQUEST_APPROVED => 'request_approved',
            NotificationType::REQUEST_REJECTED => 'request_rejected',
            NotificationType::REQUEST_RETURNED => 'request_returned',
            NotificationType::VOTING_OPENED => 'voting_opened',
            NotificationType::MFA_OTP => 'mfa_otp',
            NotificationType::PASSWORD_RESET => 'password_reset',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function variablesFor(ImportRequest $request, User $recipient, array $context): array
    {
        $request->loadMissing('bank', 'creator');

        return array_merge([
            'reference_number' => (string) $request->reference_number,
            'request_reference' => (string) $request->reference_number,
            'bank_name' => (string) ($request->bank?->name ?? ''),
            'importer_name' => (string) ($request->supplier_name ?? ''),
            'amount' => (string) ($request->amount ?? ''),
            'currency' => $this->stringValue($request->currency),
            'status' => $request->status->value,
            'action_url' => rtrim((string) config('app.url'), '/').'/requests/'.$request->id,
            'user_name' => (string) $recipient->name,
            'requestModel' => $request,
            'terminal' => false,
            'comment' => null,
            'fromRole' => '',
        ], $context);
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}

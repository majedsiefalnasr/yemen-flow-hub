<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Jobs\SendEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\ImportRequest;
use App\Models\User;
use App\Support\EmailEventId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Discriminate distinct transitions that land on the same status (e.g. a
        // request returned, resubmitted, then returned again) by the latest
        // stage-history row id, so the second legitimate email is not deduped away.
        $transitionId = $request->stageHistory()->max('id');
        $eventId = EmailEventId::forWorkflow($request->id, $request->status->value, $transitionId);

        foreach ($this->resolveRecipients($request, $definition['recipient_roles']) as $recipient) {
            $this->deliverToRecipient($type, $eventId, $request, $recipient, $context);
        }
    }

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

        if (! $this->renderWorkflowSnapshotOrRelease($delivery, $type, $request, $recipient, $context)) {
            return;
        }

        $this->queueDeliveryAfterCommit($delivery->id);
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderWorkflowSnapshotOrRelease(
        EmailDelivery $delivery,
        NotificationType $type,
        ImportRequest $request,
        User $recipient,
        array $context,
    ): bool {
        try {
            $rendered = $this->renderer->render($type, $this->variablesFor($request, $recipient, $context));
            $this->deliveries->finalize(
                $delivery,
                $rendered['subject'],
                $rendered['html'],
                $rendered['template_version_id']
            );

            return true;
        } catch (\Throwable $e) {
            // Free the reservation so a render/finalize failure does not permanently
            // consume the idempotency key (which would block any re-send). Do not
            // rethrow: a failed email must never roll back the workflow transition.
            $this->deliveries->release($delivery);
            report($e);

            return false;
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

    /**
     * @param  array<int, mixed>  $roles
     * @return Collection<int, User>
     */
    private function resolveRecipients(ImportRequest $request, array $roles): Collection
    {
        [$bankRoles, $globalCbyRoles, $orgScopedCbyRoles] = $this->partitionRecipientRoles($roles);

        return User::query()
            ->whereNotNull('email')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($bankRoles, $globalCbyRoles, $orgScopedCbyRoles, $request): void {
                if ($bankRoles !== []) {
                    $query->orWhere(function (Builder $bankQuery) use ($bankRoles, $request): void {
                        $bankQuery
                            ->where('bank_id', $request->bank_id)
                            ->whereIn('role', $bankRoles);
                    });
                }

                if ($globalCbyRoles !== []) {
                    $query->orWhereIn('role', $globalCbyRoles);
                }

                if ($orgScopedCbyRoles !== []) {
                    $query->orWhere(function (Builder $cbyOrgQuery) use ($orgScopedCbyRoles, $request): void {
                        $cbyOrgQuery
                            ->where('bank_id', $request->bank_id)
                            ->whereIn('role', $orgScopedCbyRoles);
                    });
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, mixed>  $roles
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    private function partitionRecipientRoles(array $roles): array
    {
        $bankRoles = [];
        $globalCbyRoles = [];
        $orgScopedCbyRoles = [];

        foreach ($roles as $role) {
            $roleValue = $role instanceof UserRole
                ? $role->value
                : (string) ($role->value ?? get_debug_type($role));

            $isBankRole = $role instanceof UserRole
                ? $role->isBankRole()
                : method_exists($role, 'isBankRole') && $role->isBankRole();
            $isCbyRole = $role instanceof UserRole
                ? $role->isCbyRole()
                : method_exists($role, 'isCbyRole') && $role->isCbyRole();

            if ($isBankRole) {
                $bankRoles[] = $roleValue;
            } elseif ($isCbyRole && $role instanceof UserRole && $this->isOrgScopedCbyRole($role)) {
                $orgScopedCbyRoles[] = $roleValue;
            } elseif ($isCbyRole) {
                $globalCbyRoles[] = $roleValue;
            } else {
                Log::warning('Unclassified notification recipient role skipped.', [
                    'role' => $roleValue,
                ]);
            }
        }

        return [$bankRoles, $globalCbyRoles, $orgScopedCbyRoles];
    }

    private function isOrgScopedCbyRole(UserRole $role): bool
    {
        return in_array($role, $this->orgScopedCbyRoles(), true);
    }

    /**
     * Today's CBY roles are global regulators. If a future CBY role carries a
     * bank/org scope, list it here so recipient resolution scopes it in SQL.
     *
     * @return list<UserRole>
     */
    private function orgScopedCbyRoles(): array
    {
        return [];
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

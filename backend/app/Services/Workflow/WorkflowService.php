<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Events\RequestTransitioned;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SelfReviewException;
use App\Exceptions\UnauthorizedTransitionException;
use App\Models\ImportRequest;
use App\Models\RequestStageHistory;
use App\Models\User;
use App\Listeners\SendWorkflowNotifications;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function transition(
        ImportRequest $request,
        string $action,
        User $actor,
        ?string $reason = null,
        array $metadata = []
    ): ImportRequest {
        $definitions = TransitionMap::definitions();
        $definition = $definitions[$action] ?? null;

        if (!$definition) {
            throw new InvalidTransitionException('Unknown workflow action.');
        }

        $fromStatuses = $definition['from'];
        $toStatus = $definition['to'];
        $allowedRoles = $definition['roles'];
        $newOwnerRole = $definition['next_owner'];

        if (!in_array($request->status, $fromStatuses, true)) {
            throw new InvalidTransitionException('Current status does not allow this transition.');
        }

        $autoFinalize = (bool) ($metadata['auto_finalized'] ?? false);

        if (!in_array($actor->role, $allowedRoles, true) && !$autoFinalize) {
            throw new UnauthorizedTransitionException(
                'هذا الإجراء غير مسموح لدورك الحالي. / This action is not allowed for your current role.'
            );
        }

        if ($actor->isBankUser() && $actor->bank_id !== $request->bank_id) {
            throw new UnauthorizedTransitionException('You cannot transition another bank request.');
        }

        if ($action === 'bank_approve' && $actor->id === $request->created_by) {
            throw new SelfReviewException('Reviewer cannot approve own request.');
        }

        if (in_array($action, ['support_approve', 'support_reject'], true) && !$request->isClaimedBy($actor)) {
            throw new UnauthorizedTransitionException(
                'لا يمكنك اتخاذ قرار على طلب لم تقم بحجزه. / You cannot decide on a request you have not claimed.'
            );
        }

        $transitionMetadata = $metadata;
        if ($action === 'support_claim' && $request->isClaimed() && $request->claimed_by !== $actor->id) {
            $transitionMetadata['override_previous_claim_by'] = $request->claimed_by;
        }
        if ($action === 'support_release' && !$request->isClaimed()) {
            throw new InvalidTransitionException('الطلب غير محجوز. / Request is not currently claimed.');
        }

        DB::transaction(function () use ($request, $action, $actor, $reason, $transitionMetadata, $toStatus, $newOwnerRole): void {
            $fromStatus = $request->status;
            $fromOwnerRole = $request->current_owner_role;

            $payload = [
                'status' => $toStatus,
                'current_owner_role' => $newOwnerRole,
            ];

            $timestampColumn = match ($action) {
                'submit' => 'submitted_at',
                'bank_approve' => 'bank_approved_at',
                'support_approve' => 'support_approved_at',
                'swift_upload' => 'swift_uploaded_at',
                'finalize_approved', 'finalize_rejected' => 'executive_decided_at',
                'issue_customs' => 'customs_issued_at',
                default => null,
            };

            if ($timestampColumn) {
                $payload[$timestampColumn] = now();
            }

            if ($action === 'return_to_entry') {
                $payload['revision_count'] = $request->revision_count + 1;
            }

            $request->fill($payload);
            $this->applyClaimSideEffects($request, $action, $actor);
            $request->save();

            RequestStageHistory::query()->create([
                'request_id' => $request->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'from_owner_role' => $fromOwnerRole,
                'to_owner_role' => $newOwnerRole,
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'action' => $action,
                'reason' => $reason,
                'metadata' => $transitionMetadata,
            ]);

            // Inline auto-chain: swift upload immediately enters executive voting.
            if ($action === 'swift_upload') {
                $fromStatus2 = $request->status;
                $fromOwner2 = $request->current_owner_role;

                $request->update([
                    'status' => RequestStatus::EXECUTIVE_VOTING,
                    'current_owner_role' => \App\Enums\UserRole::EXECUTIVE_MEMBER,
                ]);

                RequestStageHistory::query()->create([
                    'request_id' => $request->id,
                    'from_status' => $fromStatus2,
                    'to_status' => RequestStatus::EXECUTIVE_VOTING,
                    'from_owner_role' => $fromOwner2,
                    'to_owner_role' => \App\Enums\UserRole::EXECUTIVE_MEMBER,
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role,
                    'action' => 'start_voting',
                    'reason' => null,
                    'metadata' => ['auto_chained' => true],
                ]);

                $this->auditService->log(
                    AuditAction::STATUS_TRANSITION,
                    $actor,
                    $request,
                    [
                        'action' => 'start_voting',
                        'from_status' => $fromStatus2?->value,
                        'to_status' => RequestStatus::EXECUTIVE_VOTING->value,
                        'metadata' => ['auto_chained' => true],
                    ]
                );
            }

            $this->auditService->log(
                AuditAction::STATUS_TRANSITION,
                $actor,
                $request,
                [
                    'action' => $action,
                    'from_status' => $fromStatus?->value,
                    'to_status' => $toStatus->value,
                    'reason' => $reason,
                    'metadata' => array_merge(
                        $transitionMetadata,
                        $action === 'support_claim' ? ['claimed_until' => $request->claim_expires_at?->toISOString()] : []
                    ),
                ]
            );

            $event = new RequestTransitioned($request->refresh(), $action, $actor);
            event($event);
            app(SendWorkflowNotifications::class)->handle($event);
        });

        return $request->refresh();
    }

    private function applyClaimSideEffects(ImportRequest $request, string $action, User $actor): void
    {
        $ttl = (int) config('workflow.support_claim_ttl_hours', 24);

        match ($action) {
            'support_claim' => $request->forceFill([
                'claimed_by' => $actor->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addHours($ttl),
            ]),
            'support_release', 'support_approve', 'support_reject' => $request->forceFill([
                'claimed_by' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
            ]),
            default => null,
        };
    }
}

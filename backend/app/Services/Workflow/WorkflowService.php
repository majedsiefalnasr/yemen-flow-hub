<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Events\RequestTransitioned;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SelfReviewException;
use App\Exceptions\UnauthorizedTransitionException;
use App\Listeners\SendWorkflowNotifications;
use App\Models\ImportRequest;
use App\Models\RequestStageHistory;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\App;
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

        if (in_array($action, ['bank_approve', 'bank_reject'], true) && $actor->id === $request->created_by) {
            throw new SelfReviewException('Reviewer cannot approve or reject own request.');
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

            $actorColumn = match ($action) {
                'bank_begin_review' => 'reviewed_by',
                'bank_approve' => 'approved_by',
                'bank_reject' => 'rejected_by',
                'support_approve', 'support_reject' => 'support_reviewed_by',
                'swift_upload' => 'swift_uploaded_by',
                default => null,
            };

            if ($actorColumn) {
                $payload[$actorColumn] = $actor->id;
            }

            // submitted_by is write-once: preserves the original first submitter
            if ($action === 'submit' && $request->submitted_by === null) {
                $payload['submitted_by'] = $actor->id;
            }

            if ($action === 'submit' && $request->status === RequestStatus::DRAFT_REJECTED_INTERNAL) {
                $payload['resubmitted_by'] = $actor->id;
            }

            if ($action === 'return_to_entry') {
                $payload['revision_count'] = $request->revision_count + 1;
            }

            // Use App::instance so App::offsetUnset fully removes the binding (bind() leaves it in $bindings)
            App::instance('workflow.transition.active', true);
            try {
                $request->fill($payload);
                $this->applyClaimSideEffects($request, $action, $actor);
                $request->save();
            } finally {
                App::offsetUnset('workflow.transition.active');
            }

            // Auto-chain: BANK_APPROVED → SUPPORT_REVIEW_PENDING
            if ($action === 'bank_approve') {
                $this->autoChain($request, $actor, RequestStatus::SUPPORT_REVIEW_PENDING, UserRole::SUPPORT_COMMITTEE, 'move_to_support_queue');
            }

            // Auto-chain: SUPPORT_APPROVED → WAITING_FOR_SWIFT
            if ($action === 'support_approve') {
                $this->autoChain($request, $actor, RequestStatus::WAITING_FOR_SWIFT, UserRole::SWIFT_OFFICER, 'move_to_swift_queue');
            }

            // Auto-chain: SWIFT_UPLOADED → WAITING_FOR_VOTING_OPEN
            if ($action === 'swift_upload') {
                $this->autoChain($request, $actor, RequestStatus::WAITING_FOR_VOTING_OPEN, UserRole::COMMITTEE_DIRECTOR, 'move_to_voting_queue');
            }

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

    private function autoChain(
        ImportRequest $request,
        User $actor,
        RequestStatus $toStatus,
        ?UserRole $nextOwner,
        string $action
    ): void {
        $fromStatus = $request->status;
        $fromOwner = $request->current_owner_role;

        App::instance('workflow.transition.active', true);
        try {
            $request->forceFill([
                'status' => $toStatus,
                'current_owner_role' => $nextOwner,
            ])->save();
        } finally {
            App::offsetUnset('workflow.transition.active');
        }

        RequestStageHistory::query()->create([
            'request_id' => $request->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_owner_role' => $fromOwner,
            'to_owner_role' => $nextOwner,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role,
            'action' => $action,
            'reason' => null,
            'metadata' => ['auto_chained' => true],
        ]);

        $this->auditService->log(
            AuditAction::STATUS_TRANSITION,
            $actor,
            $request,
            [
                'action' => $action,
                'from_status' => $fromStatus?->value,
                'to_status' => $toStatus->value,
                'metadata' => ['auto_chained' => true],
            ]
        );
    }

    private function applyClaimSideEffects(ImportRequest $request, string $action, User $actor): void
    {
        $ttlMinutes = (int) config('workflow.support_claim_ttl_minutes', 15);

        match ($action) {
            'support_claim' => $request->forceFill([
                'claimed_by' => $actor->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes($ttlMinutes),
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

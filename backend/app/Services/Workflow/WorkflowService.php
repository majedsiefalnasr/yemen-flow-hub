<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VotingSessionStatus;
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
use Illuminate\Support\Facades\Cache;
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

        if (in_array($action, ['bank_approve', 'bank_reject', 'bank_return_to_intake', 'bank_reject_terminal'], true) && $actor->id === $request->created_by) {
            throw new SelfReviewException('Reviewer cannot approve, reject, or return own request.');
        }

        if (in_array($action, ['support_approve', 'support_reject', 'support_return_to_intake'], true) && !$request->isClaimedBy($actor)) {
            throw new UnauthorizedTransitionException(
                'لا يمكنك اتخاذ قرار على طلب لم تقم بحجزه. / You cannot decide on a request you have not claimed.'
            );
        }

        if ($action === 'submit') {
            $this->assertSubmitReadiness($request);
        }

        $transitionMetadata = $metadata;
        if ($action === 'support_claim' && $request->isClaimed() && $request->claimed_by !== $actor->id) {
            $transitionMetadata['override_previous_claim_by'] = $request->claimed_by;
        }
        // Allow release of expired claims (isClaimed() returns false when TTL has passed)
        if ($action === 'support_release' && !$request->isClaimed() && !$request->isClaimExpired()) {
            throw new InvalidTransitionException('الطلب غير محجوز. / Request is not currently claimed.');
        }

        DB::transaction(function () use ($request, $action, $actor, $reason, $transitionMetadata, $toStatus, $newOwnerRole): void {
            $fromStatus = $request->status;
            $fromOwnerRole = $request->current_owner_role;

            $payload = ['status' => $toStatus];
            if ($newOwnerRole !== null) {
                $payload['current_owner_role'] = $newOwnerRole;
            }

            $timestampColumn = match ($action) {
                'submit' => 'submitted_at',
                'bank_approve' => 'bank_approved_at',
                'support_approve' => 'support_approved_at',
                'swift_upload' => 'swift_uploaded_at',
                'open_voting' => 'voting_opened_at',
                'close_voting' => 'voting_closed_at',
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
                'bank_reject', 'bank_reject_terminal' => 'rejected_by',
                'support_approve', 'support_reject' => 'support_reviewed_by',
                'swift_upload' => 'swift_uploaded_by',
                'open_voting' => 'voting_opened_by',
                'close_voting' => 'voting_closed_by',
                default => null,
            };

            if ($actorColumn) {
                $payload[$actorColumn] = $actor->id;
            }

            $votingSessionStatus = match ($action) {
                'open_voting' => VotingSessionStatus::OPEN,
                'close_voting' => VotingSessionStatus::CLOSED,
                'finalize_approved', 'finalize_rejected' => VotingSessionStatus::FINALIZED,
                default => null,
            };

            if ($votingSessionStatus !== null) {
                $payload['voting_session_status'] = $votingSessionStatus;
            }

            // submitted_by is write-once: preserves the original first submitter
            if ($action === 'submit' && $request->submitted_by === null) {
                $payload['submitted_by'] = $actor->id;
            }

            if ($action === 'submit' && in_array($request->status, [RequestStatus::DRAFT_REJECTED_INTERNAL, RequestStatus::BANK_RETURNED, RequestStatus::SUPPORT_RETURNED], true)) {
                $payload['resubmitted_by'] = $actor->id;
                $payload['revision_count'] = $request->revision_count + 1;
            }

            if ($action === 'return_to_entry') {
                $payload['revision_count'] = $request->revision_count + 1;
            }

            // Use App::instance so App::offsetUnset fully removes the binding (bind() leaves it in $bindings)
            App::instance('workflow.transition.active', true);
            try {
                $request->fill($payload);
                $this->applyClaimDbSideEffects($request, $action, $actor);
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

            $event = new RequestTransitioned($request->refresh(), $action, $actor, $reason);
            event($event);
            app(SendWorkflowNotifications::class)->handle($event);
        });

        // Cache writes happen after DB commit to avoid split-brain on rollback
        $this->applyClaimCacheEffects($request, $action, $actor);

        return $request->refresh();
    }

    public function cloneRequest(ImportRequest $source, User $actor): ImportRequest
    {
        $cloneableStatuses = [
            RequestStatus::BANK_REJECTED,
            RequestStatus::SUPPORT_REJECTED,
            RequestStatus::EXECUTIVE_REJECTED,
        ];

        if (!in_array($source->status, $cloneableStatuses, true)) {
            throw new \App\Exceptions\InvalidTransitionException('Source request is not in a terminal-rejected status.');
        }

        $fields = [
            'currency', 'amount', 'supplier_name', 'goods_description', 'port_of_entry',
            'notes', 'goods_type', 'payment_terms', 'due_date', 'invoice_number',
            'invoice_date', 'origin_country', 'arrival_port', 'shipping_port',
            'customs_office', 'bl_number', 'merchant_id',
        ];

        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = $source->{$field};
        }

        $newRequest = DB::transaction(function () use ($source, $actor, $payload): ImportRequest {
            App::instance('workflow.transition.active', true);
            try {
                $cloned = ImportRequest::query()->create([
                    ...$payload,
                    'bank_id' => $source->bank_id,
                    'created_by' => $actor->id,
                    'status' => RequestStatus::DRAFT,
                    'current_owner_role' => UserRole::DATA_ENTRY,
                    'revision_count' => $source->revision_count + 1,
                ]);
            } finally {
                App::offsetUnset('workflow.transition.active');
            }

            RequestStageHistory::query()->create([
                'request_id' => $cloned->id,
                'from_status' => null,
                'to_status' => RequestStatus::DRAFT,
                'from_owner_role' => null,
                'to_owner_role' => UserRole::DATA_ENTRY,
                'actor_id' => $actor->id,
                'actor_role' => $actor->role,
                'action' => 'create',
                'reason' => null,
                'metadata' => ['cloned_from' => $source->id],
            ]);

            $this->auditService->log(
                AuditAction::REQUEST_CREATED,
                $actor,
                $cloned,
                [
                    'cloned_from' => $source->id,
                    'source_reference_number' => $source->reference_number,
                ]
            );

            return $cloned;
        });

        return $newRequest->refresh();
    }

    private function assertSubmitReadiness(ImportRequest $request): void
    {
        $requiredWizardFields = [
            'goods_type',
            'payment_terms',
            'invoice_number',
            'invoice_date',
            'origin_country',
            'arrival_port',
            'customs_office',
        ];

        $missingFields = array_values(array_filter(
            $requiredWizardFields,
            fn (string $field): bool => blank($request->{$field})
        ));

        if ($missingFields === []) {
            return;
        }

        throw new InvalidTransitionException(
            'Cannot submit request. Missing required wizard fields: '.implode(', ', $missingFields).'.'
        );
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

    private function applyClaimDbSideEffects(ImportRequest $request, string $action, User $actor): void
    {
        $ttlMinutes = (int) config('workflow.support_claim_ttl_minutes', 15);

        match ($action) {
            'support_claim' => $request->forceFill([
                'claimed_by' => $actor->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes($ttlMinutes),
            ]),
            'support_release', 'support_approve', 'support_reject', 'support_return_to_intake' => $request->forceFill([
                'claimed_by' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
            ]),
            default => null,
        };
    }

    private function applyClaimCacheEffects(ImportRequest $request, string $action, User $actor): void
    {
        $ttlMinutes = (int) config('workflow.support_claim_ttl_minutes', 15);
        $cacheKey = "support_claim:{$request->id}";

        match ($action) {
            'support_claim' => Cache::put($cacheKey, $actor->id, now()->addMinutes($ttlMinutes)),
            'support_release', 'support_approve', 'support_reject', 'support_return_to_intake' => Cache::forget($cacheKey),
            default => null,
        };
    }
}

<?php

namespace App\Services\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;

class TransitionMap
{
    /**
     * Era gate — National Committee authority reform (Epic 17-E).
     *
     * Maps a workflow action to the list of `voting_rule_version` values it remains
     * available for. Actions NOT listed here are era-agnostic and resolve for every
     * version. Resolution is keyed on the stored `voting_rule_version` column
     * (1 = legacy, 2 = new National Committee) from Story 17-C.1 — never on
     * `created_at` or any implicit signal.
     *
     * Cutover rationale: a legacy (version 1) request still in flight when Epic 17-E
     * deploys keeps its original action set so it can be carried to completion under
     * the rule it was created under. No migration, backfill, or recompute changes a
     * request's `voting_rule_version` or its available actions retroactively.
     *
     * - Story 17-E.1: the Internal Reviewer loses bank-stage reject authority on
     *   new-rule requests, so `bank_reject`/`bank_reject_terminal` are version-1 only.
     * - Story 17-E.2: the Support Committee loses independent decision authority on
     *   new-rule requests, so `support_approve`/`support_reject` are version-1 only,
     *   while `support_forward_to_executive` is version-2 only.
     */
    private const ERA_GATED_ACTIONS = [
        'bank_reject' => [1],
        'bank_reject_terminal' => [1],
        'support_approve' => [1],
        'support_reject' => [1],
        'support_forward_to_executive' => [2],
    ];

    /**
     * Whether the given action is available for a request on the given era
     * (`voting_rule_version`). Era-agnostic actions are always available.
     */
    public static function isActionAvailableForVersion(string $action, int $votingRuleVersion): bool
    {
        $allowedVersions = self::ERA_GATED_ACTIONS[$action] ?? null;

        if ($allowedVersions === null) {
            return true;
        }

        return in_array($votingRuleVersion, $allowedVersions, true);
    }

    public static function definitions(): array
    {
        return [
            'submit' => [
                'from' => [RequestStatus::DRAFT, RequestStatus::DRAFT_REJECTED_INTERNAL, RequestStatus::BANK_RETURNED, RequestStatus::SUPPORT_RETURNED],
                'to' => RequestStatus::SUBMITTED,
                'roles' => [UserRole::DATA_ENTRY, UserRole::BANK_ADMIN],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'bank_begin_review' => [
                'from' => [RequestStatus::SUBMITTED],
                'to' => RequestStatus::BANK_REVIEW,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            // Release bank review claim: returns request to SUBMITTED queue if the reviewer
            // navigates away, their session expires, or the TTL auto-expires.
            'bank_claim_release' => [
                'from' => [RequestStatus::BANK_REVIEW],
                'to' => RequestStatus::SUBMITTED,
                'roles' => [UserRole::BANK_REVIEWER, UserRole::CBY_ADMIN],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'bank_approve' => [
                'from' => [RequestStatus::BANK_REVIEW],
                'to' => RequestStatus::BANK_APPROVED,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'bank_reject' => [
                'from' => [RequestStatus::BANK_REVIEW],
                'to' => RequestStatus::DRAFT_REJECTED_INTERNAL,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'bank_return_to_intake' => [
                'from' => [RequestStatus::BANK_REVIEW],
                'to' => RequestStatus::BANK_RETURNED,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'bank_reject_terminal' => [
                'from' => [RequestStatus::BANK_REVIEW],
                'to' => RequestStatus::BANK_REJECTED,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => null,
            ],
            'return_to_entry' => [
                'from' => [RequestStatus::SUPPORT_REJECTED],
                'to' => RequestStatus::DRAFT_REJECTED_INTERNAL,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'bank_return_after_support_reject' => [
                'from' => [RequestStatus::SUPPORT_REJECTED],
                'to' => RequestStatus::DRAFT_REJECTED_INTERNAL,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'bank_finalize_rejection' => [
                'from' => [RequestStatus::SUPPORT_REJECTED],
                'to' => RequestStatus::SUPPORT_REJECTED,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'support_claim' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_PENDING],
                'to' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'support_release' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_REVIEW_PENDING,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'support_approve' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_APPROVED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SWIFT_OFFICER,
            ],
            'support_reject' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_REJECTED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            // Story 17-E.2: Support Committee forward-only (new-rule requests). Mirrors
            // support_approve — moves to SUPPORT_APPROVED and reuses the same auto-chain
            // to WAITING_FOR_SWIFT — because the workflow has no dedicated "forwarded"
            // status and the Epic 17-E enum is frozen. A mandatory comment (the $reason
            // argument) is recorded to request_stage_history + audit_logs. Era-gated to
            // version 2 only (see ERA_GATED_ACTIONS).
            'support_forward_to_executive' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_APPROVED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SWIFT_OFFICER,
            ],
            'support_return_to_intake' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_RETURNED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'swift_upload' => [
                'from' => [RequestStatus::WAITING_FOR_SWIFT],
                'to' => RequestStatus::SWIFT_UPLOADED,
                'roles' => [UserRole::SWIFT_OFFICER],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'open_voting' => [
                'from' => [RequestStatus::WAITING_FOR_VOTING_OPEN],
                'to' => RequestStatus::EXECUTIVE_VOTING_OPEN,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::EXECUTIVE_MEMBER,
            ],
            'close_voting' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING_OPEN],
                'to' => RequestStatus::EXECUTIVE_VOTING_CLOSED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'finalize_approved' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING_CLOSED],
                'to' => RequestStatus::EXECUTIVE_APPROVED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'finalize_rejected' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING_CLOSED],
                'to' => RequestStatus::EXECUTIVE_REJECTED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => null,
            ],
            'upload_fx_confirmation' => [
                'from' => [RequestStatus::EXECUTIVE_APPROVED],
                'to' => RequestStatus::FX_CONFIRMATION_PENDING,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'issue_customs' => [
                'from' => [RequestStatus::EXECUTIVE_APPROVED, RequestStatus::FX_CONFIRMATION_PENDING],
                'to' => RequestStatus::CUSTOMS_DECLARATION_ISSUED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'complete' => [
                'from' => [RequestStatus::CUSTOMS_DECLARATION_ISSUED],
                'to' => RequestStatus::COMPLETED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => null,
            ],
        ];
    }
}

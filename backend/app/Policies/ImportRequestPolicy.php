<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\ImportRequest;
use App\Models\User;

class ImportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ImportRequest $importRequest): bool
    {
        return $user->isCbyUser() || $user->bank_id === $importRequest->bank_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('request.create');
    }

    public function update(User $user, ImportRequest $importRequest): bool
    {
        return $user->hasPermission('request.create')
            && $user->bank_id === $importRequest->bank_id;
    }

    public function delete(User $user, ImportRequest $importRequest): bool
    {
        return $user->hasPermission('request.create')
            && $user->bank_id === $importRequest->bank_id;
    }

    public function clone(User $user, ImportRequest $importRequest): bool
    {
        if (! $user->is_active || ! $user->hasPermission('request.create')) {
            return false;
        }

        if ($user->bank_id !== $importRequest->bank_id) {
            return false;
        }

        return in_array($importRequest->status, [
            RequestStatus::BANK_REJECTED,
            RequestStatus::SUPPORT_REJECTED,
            RequestStatus::EXECUTIVE_REJECTED,
        ], true);
    }

    /**
     * Era gate (Epic 17-E.1): bank-stage reject authority is removed for new-rule
     * (voting_rule_version = 2) requests. Legacy (version 1) requests keep it. The gate
     * reads the stored column — never created_at — so an in-flight v1 request retains
     * its original reject path after the 17-E deploy. WorkflowService still enforces
     * role, organisation scope, and the self-review guard.
     */
    public function bankReject(User $user, ImportRequest $importRequest): bool
    {
        return (int) ($importRequest->voting_rule_version ?? 1) === 1;
    }

    public function bankRejectTerminal(User $user, ImportRequest $importRequest): bool
    {
        return (int) ($importRequest->voting_rule_version ?? 1) === 1;
    }

    /**
     * Era gate (Epic 17-E.2): the Support Committee loses independent approve/reject
     * authority on new-rule requests; only support_forward_to_executive remains.
     * Legacy (version 1) requests keep approve/reject. WorkflowService still enforces
     * role, claim ownership, and organisation scope.
     */
    public function supportApprove(User $user, ImportRequest $importRequest): bool
    {
        return (int) ($importRequest->voting_rule_version ?? 1) === 1;
    }

    public function supportReject(User $user, ImportRequest $importRequest): bool
    {
        return (int) ($importRequest->voting_rule_version ?? 1) === 1;
    }

    public function uploadDocuments(User $user, ImportRequest $importRequest): bool
    {
        return $user->hasPermission('request.create')
            && $user->bank_id === $importRequest->bank_id;
    }

    public function deleteDocuments(User $user, ImportRequest $importRequest): bool
    {
        return $user->hasPermission('request.create')
            && $user->bank_id === $importRequest->bank_id;
    }
}

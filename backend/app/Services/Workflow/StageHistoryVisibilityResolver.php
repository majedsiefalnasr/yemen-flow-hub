<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\StageHistoryVisibility;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Support\RoleCodes;

/**
 * Classifies a single `workflow_history` row for a given viewer. A user's own
 * past action on a stage they no longer (or never did) have VIEW access to is
 * preserved in sanitized form — current StagePermission grants control content
 * access, but authorship of one's own action is never fully erased.
 */
class StageHistoryVisibilityResolver
{
    public function __construct(private StagePermissionResolver $permissionResolver) {}

    public function visibilityFor(User $user, WorkflowHistoryEntry $entry): StageHistoryVisibility
    {
        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return StageHistoryVisibility::FULL;
        }

        $stage = $entry->toStage ?? $entry->fromStage;

        if ($stage !== null && $this->permissionResolver->userCanAccessStage($user, $stage, StageAccessLevel::VIEW)) {
            return StageHistoryVisibility::FULL;
        }

        if ((int) $entry->performed_by === (int) $user->getKey()) {
            return StageHistoryVisibility::SANITIZED;
        }

        return StageHistoryVisibility::HIDDEN;
    }
}

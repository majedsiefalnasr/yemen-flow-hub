<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;

class StagePermissionAudience
{
    /**
     * Resolve active users that hold EXECUTE on a given stage.
     *
     * Rows with no scoping column set are intentionally skipped so a malformed
     * permission row cannot notify every active user.
     *
     * @return array<int, int>
     */
    public function executeHolderIds(WorkflowStage $stage): array
    {
        $rows = StagePermission::query()
            ->where('stage_id', $stage->getKey())
            ->where('access_level', StageAccessLevel::EXECUTE->value)
            ->get()
            ->filter(fn ($row) => $row->organization_id !== null
                || $row->role_id !== null
                || $row->team_id !== null
                || $row->user_id !== null)
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $query = User::query()->where('is_active', true)->whereNotNull('organization_id');

        $query->where(function ($q) use ($rows) {
            foreach ($rows as $row) {
                $q->orWhere(function ($sub) use ($row) {
                    if ($row->organization_id !== null) {
                        $sub->where('organization_id', $row->organization_id);
                    }
                    if ($row->role_id !== null) {
                        $sub->whereHas('roles', fn ($rq) => $rq->where('roles.id', $row->role_id));
                    }
                    if ($row->team_id !== null) {
                        $sub->whereHas('teams', fn ($tq) => $tq->where('teams.id', $row->team_id));
                    }
                    if ($row->user_id !== null) {
                        $sub->where('users.id', $row->user_id);
                    }
                });
            }
        });

        return $query->pluck('id')->toArray();
    }

    /**
     * Count active users matching a single EXECUTE permission row (authoring feedback).
     */
    public function matchCountForPermission(StagePermission $permission): int
    {
        if ($permission->access_level !== StageAccessLevel::EXECUTE) {
            return 0;
        }

        if ($permission->organization_id === null
            && $permission->role_id === null
            && $permission->team_id === null
            && $permission->user_id === null) {
            return 0;
        }

        $query = User::query()->where('is_active', true)->whereNotNull('organization_id');
        $query->where(function ($sub) use ($permission) {
            if ($permission->organization_id !== null) {
                $sub->where('organization_id', $permission->organization_id);
            }
            if ($permission->role_id !== null) {
                $sub->whereHas('roles', fn ($rq) => $rq->where('roles.id', $permission->role_id));
            }
            if ($permission->team_id !== null) {
                $sub->whereHas('teams', fn ($tq) => $tq->where('teams.id', $permission->team_id));
            }
            if ($permission->user_id !== null) {
                $sub->where('users.id', $permission->user_id);
            }
        });

        return (int) $query->count();
    }
}

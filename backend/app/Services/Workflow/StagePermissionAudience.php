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

        $query = User::query()->where('is_active', true);

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
}

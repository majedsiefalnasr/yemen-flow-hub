<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Support\GovernanceExecutorSimulation;

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
    public function executeHolderIds(WorkflowStage $stage, ?GovernanceExecutorSimulation $simulation = null): array
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

        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('organization_id')
            ->whereHas('organization', fn ($oq) => $oq->where('is_active', true));

        if ($simulation?->deactivatingOrganizationId() !== null) {
            $query->where('organization_id', '!=', $simulation->deactivatingOrganizationId());
        }

        $query->where(function ($q) use ($rows, $simulation) {
            foreach ($rows as $row) {
                if ($simulation !== null
                    && $row->organization_id !== null
                    && $simulation->isDeactivatingOrganization((int) $row->organization_id)) {
                    continue;
                }
                if ($simulation !== null
                    && $row->user_id !== null
                    && $simulation->isDeactivatingUser((int) $row->user_id)) {
                    continue;
                }

                $q->orWhere(function ($sub) use ($row, $simulation) {
                    if ($row->organization_id !== null) {
                        $sub->where('organization_id', $row->organization_id);
                    }
                    if ($row->role_id !== null) {
                        $sub->whereHas('roles', function ($rq) use ($row, $simulation) {
                            $rq->where('roles.id', $row->role_id)->where('roles.is_active', true);
                            if ($simulation !== null && $simulation->isDeactivatingRole((int) $row->role_id)) {
                                $rq->whereRaw('1 = 0');
                            }
                        });
                    }
                    if ($row->team_id !== null) {
                        $sub->whereHas('teams', function ($tq) use ($row, $simulation) {
                            $tq->where('teams.id', $row->team_id)->where('teams.is_active', true);
                            if ($simulation !== null && $simulation->isDeactivatingTeam((int) $row->team_id)) {
                                $tq->whereRaw('1 = 0');
                            }
                        });
                    }
                    if ($row->user_id !== null) {
                        $sub->where('users.id', $row->user_id);
                    }
                });
            }
        });

        $userIds = $query->pluck('id')->toArray();

        if ($simulation !== null) {
            return array_values(array_filter(
                $userIds,
                fn (int $userId): bool => ! $simulation->isDeactivatingUser($userId),
            ));
        }

        return $userIds;
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

        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('organization_id')
            ->whereHas('organization', fn ($oq) => $oq->where('is_active', true));
        $query->where(function ($sub) use ($permission) {
            if ($permission->organization_id !== null) {
                $sub->where('organization_id', $permission->organization_id);
            }
            if ($permission->role_id !== null) {
                $sub->whereHas('roles', fn ($rq) => $rq->where('roles.id', $permission->role_id)->where('roles.is_active', true));
            }
            if ($permission->team_id !== null) {
                $sub->whereHas('teams', fn ($tq) => $tq->where('teams.id', $permission->team_id)->where('teams.is_active', true));
            }
            if ($permission->user_id !== null) {
                $sub->where('users.id', $permission->user_id);
            }
        });

        return (int) $query->count();
    }
}

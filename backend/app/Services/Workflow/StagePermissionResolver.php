<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;

/**
 * The sole routing source for the dynamic engine (FR-WD5). Evaluates a user's
 * access on a stage purely from `stage_permissions` rows — no parallel role-code
 * gating.
 *
 * Match semantics (architecture §4.5):
 *  - Within a single row: every SET field (organization_id, team_id, role_id,
 *    user_id) must match the user's identity. Null fields are wildcards. (AND)
 *  - Across rows: the user is granted access if ANY row matches. (OR)
 *  - EXECUTE implies VIEW.
 *
 * This service is identity-set based so it can be unit tested without a full DB
 * and reused by the queue (18.5.3) and transition access (18.5.4).
 */
class StagePermissionResolver
{
    /**
     * Does the user hold at least $required access on the given stage?
     */
    public function userCanAccessStage(
        User $user,
        WorkflowStage $stage,
        StageAccessLevel $required = StageAccessLevel::VIEW,
    ): bool {
        $rows = StagePermission::query()->where('stage_id', $stage->getKey())->get();

        return $this->identityMatchesAny($this->identityFor($user), $rows, $required);
    }

    /**
     * Stage ids on which the user holds at least $required access. Used by the queue
     * to scope which stages' requests a user may see/act on.
     *
     * @return array<int, int>
     */
    public function accessibleStageIds(User $user, StageAccessLevel $required = StageAccessLevel::VIEW): array
    {
        $identity = $this->identityFor($user);

        return StagePermission::query()
            ->get()
            ->groupBy('stage_id')
            ->filter(fn (Collection $rows) => $this->identityMatchesAny($identity, $rows, $required))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Pure evaluation entry point — exposed for unit testing with synthetic rows
     * and identities, no DB required.
     *
     * @param  array{organization_id: int|null, team_ids: array<int>, role_ids: array<int>, user_id: int}  $identity
     * @param  iterable<StagePermission>  $rows
     */
    public function identityMatchesAny(array $identity, iterable $rows, StageAccessLevel $required): bool
    {
        if ($identity['organization_id'] === null) {
            return false;
        }

        foreach ($rows as $row) {
            if ($this->rowMatches($identity, $row, $required)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{organization_id: int|null, team_ids: array<int>, role_ids: array<int>, user_id: int}  $identity
     */
    private function rowMatches(array $identity, StagePermission $row, StageAccessLevel $required): bool
    {
        if (! $row->access_level->satisfies($required)) {
            return false;
        }

        if ($row->organization_id !== null && $row->organization_id !== $identity['organization_id']) {
            return false;
        }
        if ($row->team_id !== null && ! in_array($row->team_id, $identity['team_ids'], true)) {
            return false;
        }
        if ($row->role_id !== null && ! in_array($row->role_id, $identity['role_ids'], true)) {
            return false;
        }
        if ($row->user_id !== null && $row->user_id !== $identity['user_id']) {
            return false;
        }

        // A row with all-null fields matches everyone; otherwise the set fields all
        // matched above (AND).
        return true;
    }

    /**
     * @return array{organization_id: int|null, team_ids: array<int>, role_ids: array<int>, user_id: int}
     */
    private function identityFor(User $user): array
    {
        return [
            'organization_id' => $user->organization_id !== null ? (int) $user->organization_id : null,
            'team_ids' => $user->teams()->where('teams.is_active', true)->pluck('teams.id')->map(fn ($id) => (int) $id)->all(),
            'role_ids' => $user->roles()->where('roles.is_active', true)->pluck('roles.id')->map(fn ($id) => (int) $id)->all(),
            'user_id' => (int) $user->getKey(),
        ];
    }
}

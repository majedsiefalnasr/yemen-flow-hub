<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;

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

        // A user with no organization can never match a row (mirrors the PHP
        // evaluator's early guard in identityMatchesAny). Short-circuit before
        // touching the DB.
        if ($identity['organization_id'] === null) {
            return [];
        }

        // ARCH-001: push the identity match into SQL. Instead of hydrating the
        // whole stage_permissions table into PHP and grouping/filtering in
        // memory (cost scaled with total permission rows across all workflows),
        // return the distinct stage_ids of rows that match this user's identity.
        // The WHERE clause reproduces the pure-PHP evaluator exactly:
        //   - per set field: NULL (wildcard) OR equals/in the user's identity
        //   - all set fields ANDed within a row
        //   - a stage is accessible if ANY of its rows match (OR) — DISTINCT
        //   - EXECUTE⊃VIEW: only constrain access_level when EXECUTE is required
        return StagePermission::query()
            ->where(fn (QueryBuilder $q) => $this->applyIdentityMatch($q, $identity, $required))
            ->distinct()
            ->pluck('stage_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * SQL translation of rowMatches(): a stage_permissions row matches when every
     * SET field equals the user's identity and NULL fields are wildcards, with
     * EXECUTE⊃VIEW. Kept in lockstep with rowMatches()/identityMatchesAny(); the
     * AccessibleStageIdsParityTest pins the two paths to identical output.
     *
     * @param  array{organization_id: int|null, team_ids: array<int>, role_ids: array<int>, user_id: int}  $identity
     */
    private function applyIdentityMatch(QueryBuilder $query, array $identity, StageAccessLevel $required): void
    {
        // EXECUTE⊃VIEW: a VIEW request is satisfied by any level, so only filter
        // when EXECUTE is explicitly required. Matches StageAccessLevel::satisfies.
        if ($required === StageAccessLevel::EXECUTE) {
            $query->where('access_level', StageAccessLevel::EXECUTE->value);
        }

        // organization_id IS NULL OR organization_id = :org
        $query->where(fn (QueryBuilder $q) => $q
            ->whereNull('organization_id')
            ->orWhere('organization_id', $identity['organization_id']));

        // team_id IS NULL OR team_id IN (:team_ids). An empty identity list means
        // no non-null team row can match, so only the NULL (wildcard) branch
        // remains — never emit `IN ()`.
        $query->where(fn (QueryBuilder $q) => $this->applyNullableSetMatch($q, 'team_id', $identity['team_ids']));

        // role_id IS NULL OR role_id IN (:role_ids)
        $query->where(fn (QueryBuilder $q) => $this->applyNullableSetMatch($q, 'role_id', $identity['role_ids']));

        // user_id IS NULL OR user_id = :user_id
        $query->where(fn (QueryBuilder $q) => $q
            ->whereNull('user_id')
            ->orWhere('user_id', $identity['user_id']));
    }

    /**
     * `$column IS NULL OR $column IN ($values)`, collapsing to just the NULL
     * branch when $values is empty so no `IN ()` is emitted.
     *
     * @param  array<int>  $values
     */
    private function applyNullableSetMatch(QueryBuilder $query, string $column, array $values): void
    {
        $query->whereNull($column);

        if ($values !== []) {
            $query->orWhereIn($column, $values);
        }
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
            // Active pivot AND active role record only — a deactivated role
            // assignment must not authorize even if the role record stays active
            // (M3 / RBAC-001).
            'role_ids' => $user->roles()->wherePivot('is_active', true)->where('roles.is_active', true)->pluck('roles.id')->map(fn ($id) => (int) $id)->all(),
            'user_id' => (int) $user->getKey(),
        ];
    }
}

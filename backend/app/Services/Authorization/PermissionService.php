<?php

namespace App\Services\Authorization;

use App\Enums\OrganizationClassification;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\StagePermission;
use App\Models\User;
use App\Services\Workflow\StagePermissionResolver;
use App\Support\RequestCreationGate;
use App\Support\RoleCodes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function __construct(private readonly StagePermissionResolver $stagePermissionResolver) {}

    /**
     * Build screen → capabilities map from screen_permissions table (governance roles).
     *
     * The `requests` key is overlaid with a per-user re-derivation
     * (derivedRequestsCapabilitiesForUser) on top of the role-cached base,
     * because the role-only cache cannot resolve stage_permissions rows
     * scoped to a team (see derivedRequestsCapabilities's docblock). A real
     * user's team memberships are known here, so this overlay recovers
     * `requests` access for team-scoped rows without changing the role-id
     * cache's contract or its callers.
     */
    public function screenPermissionsForUser(User $user): array
    {
        $governanceRole = $user->role();

        if ($governanceRole === null) {
            return [];
        }

        $result = $this->screenPermissionsForGovernanceRole($governanceRole->id);

        $derived = $this->derivedRequestsCapabilitiesForUser($user);
        $requestsCaps = [];
        if ($derived['view']) {
            $requestsCaps[] = 'VIEW';
        }
        if ($derived['add']) {
            $requestsCaps[] = 'CREATE';
        }
        if ($derived['edit']) {
            $requestsCaps[] = 'UPDATE';
        }

        if (! empty($requestsCaps)) {
            $existing = $result['requests'] ?? [];
            $result['requests'] = array_values(array_unique([...$existing, ...$requestsCaps]));
        }

        return $result;
    }

    public function screenPermissionsForGovernanceRole(int $roleId): array
    {
        $cacheKey = "screen_permissions.role.{$roleId}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($roleId): array {
            $rows = DB::table('screen_permissions')
                ->join('screens', 'screens.id', '=', 'screen_permissions.screen_id')
                ->where('screen_permissions.role_id', $roleId)
                ->where('screens.key', '!=', 'requests')
                ->select('screens.key', 'screen_permissions.capability')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $result[$row->key][] = $row->capability;
            }

            foreach ($result as $key => $caps) {
                $result[$key] = array_values(array_unique($caps));
            }

            $derived = $this->derivedRequestsCapabilities([$roleId])[$roleId];
            $requestsCaps = [];
            if ($derived['view']) {
                $requestsCaps[] = 'VIEW';
            }
            if ($derived['add']) {
                $requestsCaps[] = 'CREATE';
            }
            if ($derived['edit']) {
                $requestsCaps[] = 'UPDATE';
            }
            if (! empty($requestsCaps)) {
                $result['requests'] = $requestsCaps;
            }

            ksort($result);

            return $result;
        });
    }

    /**
     * Derive requests-screen access per role from stage_permissions on
     * currently-published workflow versions. This is the single source of
     * truth for `requests` screen capability — used by both the
     * screen-permissions matrix display and runtime enforcement.
     *
     * Because at most one WorkflowVersion per WorkflowDefinition can be
     * PUBLISHED at a time (WorkflowDesignerService::publishVersion() archives
     * the prior published version of a definition on every new publish),
     * this iterates every published version across every definition rather
     * than picking a single "latest" one — a role's access must be evaluated
     * against ALL live definitions, not just whichever version happens to
     * sort last.
     *
     * Row matching reuses the org+role identity-set semantics of
     * StagePermissionResolver::rowMatches() (null field = wildcard, AND
     * within a row, OR across rows) but only for organization_id/role_id,
     * since a Role's organization_id is always resolvable from the role id
     * alone (`roles.organization_id` is a required non-null FK).
     *
     * Limitation: stage_permissions rows scoped to a team (team_id IS NOT
     * NULL), with or without a role_id, are NOT resolvable from a role id
     * alone — a role's members are not confined to one team, so "does this
     * role match an org+team row" has no single per-role answer that a
     * role-id-keyed cache (screenPermissionsForGovernanceRole, 1-hour TTL)
     * can safely store. Such rows are treated as non-matching here,
     * permanently, by design. This under-counts (false negative) rather
     * than over-granting, which is the safe default; it only affects what
     * the frontend chrome shows via /auth/me — StagePermissionResolver
     * remains the real per-user enforcement gate on every actual
     * transition/queue endpoint.
     *
     * @param  array<int>  $roleIds
     * @return array<int, array{view: bool, add: bool, edit: bool}>
     */
    public function derivedRequestsCapabilities(array $roleIds): array
    {
        $result = array_fill_keys($roleIds, ['view' => false, 'add' => false, 'edit' => false]);
        if (empty($roleIds)) {
            return $result;
        }

        $systemAdminRoleIds = DB::table('roles')
            ->whereIn('id', $roleIds)
            ->where('code', RoleCodes::SYSTEM_ADMIN)
            ->pluck('id');

        foreach ($systemAdminRoleIds as $roleId) {
            $result[$roleId] = ['view' => true, 'add' => false, 'edit' => false];
        }

        $roleOrganizations = DB::table('roles')
            ->whereIn('id', $roleIds)
            ->pluck('organization_id', 'id');

        $bankingOrganizationIds = DB::table('organizations')
            ->where('classification', OrganizationClassification::BANKING_SECTOR->value)
            ->pluck('id')
            ->flip();

        $publishedVersionIds = DB::table('workflow_versions')
            ->where('state', WorkflowVersionState::PUBLISHED->value)
            ->pluck('id');

        if ($publishedVersionIds->isEmpty()) {
            return $result;
        }

        $stages = DB::table('workflow_stages')
            ->whereIn('workflow_version_id', $publishedVersionIds)
            ->where('status', 'ACTIVE')
            ->select('id', 'workflow_version_id', 'is_initial')
            ->get();

        if ($stages->isEmpty()) {
            return $result;
        }

        $initialStageIds = $stages
            ->filter(fn ($stage) => (bool) $stage->is_initial)
            ->pluck('id')
            ->flip();

        $permissionRows = DB::table('stage_permissions')
            ->whereIn('stage_id', $stages->pluck('id'))
            ->select('stage_id', 'organization_id', 'team_id', 'role_id', 'access_level')
            ->get();

        if ($permissionRows->isEmpty()) {
            return $result;
        }

        foreach ($roleIds as $roleId) {
            $roleOrganizationId = $roleOrganizations[$roleId] ?? null;
            if ($roleOrganizationId === null) {
                continue;
            }

            $view = false;
            $edit = false;
            $add = false;

            foreach ($permissionRows as $row) {
                // team_id-scoped rows are not resolvable from a role id alone: a
                // role's members are not confined to one team, so this method
                // treats org+team rows as non-matching here by design (see the
                // docblock above and StagePermissionResolver for the real
                // per-user enforcement, which does resolve team membership).
                if ($row->team_id !== null) {
                    continue;
                }

                $organizationMatches = $row->organization_id === null || $row->organization_id === $roleOrganizationId;
                $roleMatches = $row->role_id === null || $row->role_id === $roleId;

                if (! $organizationMatches || ! $roleMatches) {
                    continue;
                }

                $view = true;

                if ($row->access_level === 'EXECUTE') {
                    $edit = true;

                    if ($initialStageIds->has($row->stage_id)
                        && $bankingOrganizationIds->has($roleOrganizationId)) {
                        $add = true;
                    }
                }
            }

            if ($view) {
                $result[$roleId] = ['view' => $view, 'add' => $add, 'edit' => $edit];
            }
        }

        return $result;
    }

    /**
     * Derive requests-screen access for one real user from stage_permissions
     * on currently-published workflow versions, resolving the user's actual
     * team memberships via StagePermissionResolver — the counterpart to
     * derivedRequestsCapabilities(), which is role-id-only and therefore
     * cannot see team-scoped rows. Used only by screenPermissionsForUser();
     * not cached (identical 1-hour TTL role cache still covers the
     * non-team-scoped base, so this only runs the extra per-user query when
     * building /auth/me's response, not on every permission check).
     *
     * @return array{view: bool, add: bool, edit: bool}
     */
    public function derivedRequestsCapabilitiesForUser(User $user): array
    {
        $result = ['view' => false, 'add' => false, 'edit' => false];

        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            $result['view'] = true;
        }

        $publishedVersionIds = DB::table('workflow_versions')
            ->where('state', WorkflowVersionState::PUBLISHED->value)
            ->pluck('id');

        if ($publishedVersionIds->isEmpty()) {
            return $result;
        }

        $stages = DB::table('workflow_stages')
            ->whereIn('workflow_version_id', $publishedVersionIds)
            ->where('status', 'ACTIVE')
            ->select('id', 'is_initial')
            ->get();

        if ($stages->isEmpty()) {
            return $result;
        }

        $stageIds = $stages->pluck('id');
        $initialStageIds = $stages
            ->filter(fn ($stage) => (bool) $stage->is_initial)
            ->pluck('id')
            ->flip();

        $rows = StagePermission::query()->whereIn('stage_id', $stageIds)->get();

        if ($rows->isEmpty()) {
            return $result;
        }

        $identity = [
            'organization_id' => $user->organization_id !== null ? (int) $user->organization_id : null,
            'team_ids' => $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all(),
            'role_ids' => $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all(),
            'user_id' => (int) $user->getKey(),
        ];

        if ($identity['organization_id'] === null) {
            return $result;
        }

        $canCreate = RequestCreationGate::userCanCreateRequests($user);

        $rowsByStage = $rows->groupBy('stage_id');

        foreach ($rowsByStage as $stageId => $stageRows) {
            if ($this->stagePermissionResolver->identityMatchesAny($identity, $stageRows, StageAccessLevel::VIEW)) {
                $result['view'] = true;
            }

            if ($this->stagePermissionResolver->identityMatchesAny($identity, $stageRows, StageAccessLevel::EXECUTE)) {
                $result['edit'] = true;

                if ($initialStageIds->has($stageId) && $canCreate) {
                    $result['add'] = true;
                }
            }
        }

        return $result;
    }

    /**
     * Whether the user holds a specific capability on a specific screen,
     * derived from the data-driven screen_permissions catalog (never role codes).
     *
     * Exception: system_admin never holds MANAGE on the merchants screen,
     * even if a screen_permissions row grants it -- system_admin may
     * inspect and export merchant data but never create/update/delete it.
     * This is enforced here (not just by omission from seed data) so it
     * cannot be bypassed by a future manual grant.
     */
    public function userHasCapability(User $user, string $screenKey, string $capability): bool
    {
        if ($screenKey === 'merchants' && $capability === 'MANAGE' && $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return false;
        }

        $sp = $this->screenPermissionsForUser($user);

        return in_array($capability, $sp[$screenKey] ?? [], true);
    }

    public function capabilitiesForUser(User $user): array
    {
        $sp = $this->screenPermissionsForUser($user);

        return [
            'manage_users' => in_array('MANAGE', $sp['users'] ?? [], true),
            'manage_banks' => in_array('MANAGE', $sp['banks'] ?? [], true),
            'manage_roles' => in_array('MANAGE', $sp['roles'] ?? [], true),
            'view_reports' => in_array('VIEW', $sp['reports'] ?? [], true),
            'view_audit' => in_array('VIEW', $sp['audit'] ?? [], true),
        ];
    }

    public function clearScreenPermissionCache(int $roleId): void
    {
        Cache::forget("screen_permissions.role.{$roleId}");
    }

    /**
     * Clear cached screen-permissions maps for every role. Call this whenever
     * the published workflow version or its stage_permissions change, since
     * `requests` capability is derived from that data for all roles at once
     * (not just one role, unlike a manual grant edit).
     */
    public function clearAllScreenPermissionCaches(): void
    {
        $roleIds = DB::table('roles')->pluck('id');
        foreach ($roleIds as $roleId) {
            $this->clearScreenPermissionCache($roleId);
        }
    }
}

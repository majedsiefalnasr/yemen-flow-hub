<?php

namespace App\Services\Authorization;

use App\Enums\WorkflowVersionState;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Build screen → capabilities map from screen_permissions table (governance roles).
     */
    public function screenPermissionsForUser(User $user): array
    {
        $governanceRole = $user->role();

        if ($governanceRole === null) {
            return [];
        }

        return $this->screenPermissionsForGovernanceRole($governanceRole->id);
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
     * Derive requests-screen access per role from stage_permissions on the
     * published workflow version. This is the single source of truth for
     * `requests` screen capability — used by both the screen-permissions
     * matrix display and runtime enforcement.
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
            ->where('code', 'system_admin')
            ->pluck('id');

        foreach ($systemAdminRoleIds as $roleId) {
            $result[$roleId] = ['view' => true, 'add' => false, 'edit' => false];
        }

        $publishedVersionId = DB::table('workflow_versions')
            ->where('state', WorkflowVersionState::PUBLISHED->value)
            ->orderByDesc('version_number')
            ->value('id');

        if ($publishedVersionId === null) {
            return $result;
        }

        $stageIds = DB::table('workflow_stages')
            ->where('workflow_version_id', $publishedVersionId)
            ->where('status', 'ACTIVE')
            ->pluck('id', 'id');

        if ($stageIds->isEmpty()) {
            return $result;
        }

        $initialStageId = DB::table('workflow_stages')
            ->where('workflow_version_id', $publishedVersionId)
            ->where('is_initial', true)
            ->value('id');

        $assignments = DB::table('stage_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('stage_id', $stageIds)
            ->select('role_id', 'stage_id', 'access_level')
            ->get()
            ->groupBy('role_id')
            ->map(fn ($items) => $items->keyBy('stage_id')->map(fn ($item) => $item->access_level))
            ->all();

        foreach ($roleIds as $roleId) {
            $perRole = $assignments[$roleId] ?? collect();
            if ($perRole->isEmpty()) {
                continue;
            }

            $view = $perRole->isNotEmpty();
            $edit = $perRole->contains(fn (string $level) => $level === 'EXECUTE');
            $add = $initialStageId !== null
                && ($perRole->get($initialStageId) === 'EXECUTE');

            $result[$roleId] = ['view' => $view, 'add' => $add, 'edit' => $edit];
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
        if ($screenKey === 'merchants' && $capability === 'MANAGE' && $user->hasRoleCode('system_admin')) {
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

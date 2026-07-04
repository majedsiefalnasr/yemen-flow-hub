<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    // Legacy SCREEN_MAP kept for userCan() / permissionsForRole() back-compat
    private const SCREEN_MAP = [
        'request' => 'requests',
        'swift' => 'requests',
        'voting' => 'requests',
        'customs' => 'requests',
        'reports' => 'reports',
        'audit' => 'audit',
        'merchants' => 'merchants',
        'users' => 'users',
        'entities' => 'banks',
        'docrules' => 'reference_data',
        'roles' => 'roles',
    ];

    public function userCan(User $user, string $permissionSlug): bool
    {
        if ($user->role === null) {
            return false;
        }

        $slugs = Cache::remember(
            $this->cacheKey($user->role),
            now()->addHour(),
            function () use ($user): array {
                return DB::table('role_permissions')
                    ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->where('role_permissions.role', $user->role->value)
                    ->pluck('permissions.slug')
                    ->toArray();
            }
        );

        return in_array($permissionSlug, $slugs, true);
    }

    public function permissionsForRole(UserRole $role): Collection
    {
        return Permission::query()
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role', $role->value)
            ->select('permissions.*')
            ->orderBy('permissions.group')
            ->orderBy('permissions.slug')
            ->get();
    }

    /**
     * Build screen → capabilities map from screen_permissions table (governance roles).
     * Falls back to legacy derivation for users without a governance role.
     */
    public function screenPermissionsForUser(User $user): array
    {
        $governanceRole = $user->role();

        if ($governanceRole) {
            return $this->screenPermissionsForGovernanceRole($governanceRole->id);
        }

        return $this->legacyScreenPermissionsForUser($user);
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
     */
    public function userHasCapability(User $user, string $screenKey, string $capability): bool
    {
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

    public function rolesForPermission(string $permissionSlug): array
    {
        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.slug', $permissionSlug)
            ->pluck('role_permissions.role')
            ->toArray();
    }

    public function clearRoleCache(UserRole|string $role): void
    {
        $value = $role instanceof UserRole ? $role->value : $role;
        Cache::forget("permissions.role.{$value}");
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

    private function legacyScreenPermissionsForUser(User $user): array
    {
        $result = [];

        if ($user->role === null) {
            return $result;
        }

        foreach ($this->permissionsForRole($user->role) as $permission) {
            [$subject, $action] = array_pad(explode('.', $permission->slug, 2), 2, 'view');
            $screen = self::SCREEN_MAP[$subject] ?? $subject;
            $capabilities = match ($action) {
                'create' => ['VIEW', 'CREATE'],
                'manage' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'view', 'review', 'claim', 'cast' => ['VIEW'],
                'approve', 'reject', 'upload', 'issue', 'finalize' => ['VIEW', 'UPDATE'],
                default => ['VIEW'],
            };

            $result[$screen] = array_values(array_unique([
                ...($result[$screen] ?? []),
                ...$capabilities,
            ]));
        }

        if (isset($result['roles'])) {
            foreach (['organizations', 'teams'] as $screen) {
                $result[$screen] = $result['roles'];
            }
        }

        ksort($result);

        return $result;
    }

    private function cacheKey(UserRole $role): string
    {
        return "permissions.role.{$role->value}";
    }
}

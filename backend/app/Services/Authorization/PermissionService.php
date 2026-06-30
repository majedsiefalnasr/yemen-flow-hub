<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
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
                ->select('screens.key', 'screen_permissions.capability')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $result[$row->key][] = $row->capability;
            }

            foreach ($result as $key => $caps) {
                $result[$key] = array_values(array_unique($caps));
            }

            ksort($result);

            return $result;
        });
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

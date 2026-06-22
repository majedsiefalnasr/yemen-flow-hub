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

    public function screenPermissionsForUser(User $user): array
    {
        $result = [];

        if ($user->role === null) {
            // A user without a resolved legacy role has no derived screen
            // permissions (fail closed) rather than throwing on /auth/me.
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

    public function capabilitiesForUser(User $user): array
    {
        return [
            'manage_users' => $this->userCan($user, 'users.manage'),
            'manage_banks' => $this->userCan($user, 'entities.manage'),
            'manage_roles' => $this->userCan($user, 'roles.manage'),
            'view_reports' => $this->userCan($user, 'reports.view'),
            'view_audit' => $this->userCan($user, 'audit.view'),
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

    private function cacheKey(UserRole $role): string
    {
        return "permissions.role.{$role->value}";
    }
}

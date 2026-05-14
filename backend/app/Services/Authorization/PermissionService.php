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
    public function userCan(User $user, string $permissionSlug): bool
    {
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

<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\UserRoleMapper;

trait FindsGovernanceUsers
{
    protected function firstUserWithRole(UserRole $role): User
    {
        $roleCode = UserRoleMapper::roleCodeFor($role);

        return User::query()
            ->whereHas(
                'roles',
                fn ($query) => $query
                    ->where('roles.code', $roleCode)
                    ->where('user_roles.is_active', true),
            )
            ->orderBy('id')
            ->firstOrFail();
    }

    protected function firstUserWithoutRole(UserRole $role): User
    {
        $roleCode = UserRoleMapper::roleCodeFor($role);

        return User::query()
            ->whereDoesntHave(
                'roles',
                fn ($query) => $query
                    ->where('roles.code', $roleCode)
                    ->where('user_roles.is_active', true),
            )
            ->orderBy('id')
            ->firstOrFail();
    }
}

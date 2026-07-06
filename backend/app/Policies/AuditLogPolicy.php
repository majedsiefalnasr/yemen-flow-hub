<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Authorization\DataScope;
use App\Services\Authorization\PermissionService;

class AuditLogPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        if (! $this->permissionService->userHasCapability($user, 'audit', 'VIEW')) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return DataScope::forUser($user)->systemWide;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }
}

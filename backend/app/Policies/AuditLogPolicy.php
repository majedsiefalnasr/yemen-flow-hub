<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Authorization\PermissionService;

class AuditLogPolicy
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function viewAny(User $user): bool
    {
        return $this->permissionService->userHasCapability($user, 'audit', 'VIEW');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->permissionService->userHasCapability($user, 'audit', 'VIEW');
    }
}

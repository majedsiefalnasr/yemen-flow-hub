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

        $scope = DataScope::forUser($user);

        // SEC-002: a bank-scoped user with the audit.VIEW capability sees
        // their own bank's rows (enforced at the query level in the
        // controller); systemWide sees everything; neither means no access.
        return $scope->systemWide || $scope->ownBankId !== null;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }
}

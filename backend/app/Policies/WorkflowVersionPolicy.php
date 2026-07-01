<?php

namespace App\Policies;

use App\Enums\WorkflowVersionState;
use App\Models\User;
use App\Models\WorkflowVersion;

class WorkflowVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('workflow.design');
    }

    public function view(User $user, WorkflowVersion $version): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Only DRAFT versions may be mutated. PUBLISHED and ARCHIVED versions are
     * immutable at the policy layer (returns HTTP 403 before the service runs).
     */
    public function update(User $user, WorkflowVersion $version): bool
    {
        if ($version->state === WorkflowVersionState::PUBLISHED) {
            return false;
        }

        return $this->viewAny($user);
    }

    /**
     * Archiving is a state transition on a PUBLISHED version; it has its own
     * gate so that the update() guard (DRAFT-only) does not block it.
     */
    public function archive(User $user, WorkflowVersion $version): bool
    {
        return $this->viewAny($user);
    }

    public function clone(User $user, WorkflowVersion $version): bool
    {
        return $this->viewAny($user);
    }
}

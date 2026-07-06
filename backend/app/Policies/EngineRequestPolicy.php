<?php

namespace App\Policies;

use App\Enums\StageAccessLevel;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Workflow\StagePermissionResolver;
use App\Support\RoleCodes;

class EngineRequestPolicy
{
    public function __construct(
        private StagePermissionResolver $resolver,
    ) {}

    public function view(User $user, EngineRequest $request): bool
    {
        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return true;
        }

        if (! $this->inScope($user, $request)) {
            return false;
        }

        return $this->resolver->userCanAccessStage(
            $user,
            $request->currentStage,
            StageAccessLevel::VIEW,
        );
    }

    public function execute(User $user, EngineRequest $request): bool
    {
        if (! $this->inScope($user, $request)) {
            return false;
        }

        if (! $request->isActive()) {
            return false;
        }

        return $this->resolver->userCanAccessStage(
            $user,
            $request->currentStage,
            StageAccessLevel::EXECUTE,
        );
    }

    /**
     * Abandon delegates guard checks to EngineTransitionService::abandonDraft so
     * clients receive the spec error codes (REQUEST_CLOSED, ABANDON_NOT_AVAILABLE, …).
     */
    public function abandon(User $user, EngineRequest $request): bool
    {
        return $this->inScope($user, $request);
    }

    private function inScope(User $user, EngineRequest $request): bool
    {
        if ($user->bank_id === null) {
            return true;
        }

        return (int) $user->bank_id === (int) $request->bank_id;
    }
}

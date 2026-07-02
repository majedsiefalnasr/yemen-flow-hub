<?php

namespace App\Policies;

use App\Enums\StageAccessLevel;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Workflow\StagePermissionResolver;

class EngineRequestPolicy
{
    public function __construct(
        private StagePermissionResolver $resolver,
    ) {}

    public function view(User $user, EngineRequest $request): bool
    {
        if ($user->isSystemAdmin()) {
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

    private function inScope(User $user, EngineRequest $request): bool
    {
        if ($user->bank_id === null) {
            return true;
        }

        return (int) $user->bank_id === (int) $request->bank_id;
    }
}

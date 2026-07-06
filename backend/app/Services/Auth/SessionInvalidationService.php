<?php

namespace App\Services\Auth;

use App\Models\User;

class SessionInvalidationService
{
    public function __construct(
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly StepUpService $stepUpService,
    ) {}

    public function invalidate(User $user): void
    {
        $user->tokens()->delete();
        $this->trustedDeviceService->revokeAll($user);
        $this->stepUpService->clearStepUp($user);
    }

    public function revokeAllSessions(User $user): void
    {
        $this->invalidate($user);
    }
}

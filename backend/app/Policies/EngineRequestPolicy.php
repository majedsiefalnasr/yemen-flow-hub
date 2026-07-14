<?php

namespace App\Policies;

use App\Enums\StageAccessLevel;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Authorization\DataScope;
use App\Services\Customs\FxConfirmationAuthorizationService;
use App\Services\Workflow\StagePermissionResolver;
use App\Support\RoleCodes;

class EngineRequestPolicy
{
    public function __construct(
        private StagePermissionResolver $resolver,
        private FxConfirmationAuthorizationService $fxAuthorization,
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
    public function uploadSignedFx(User $user, EngineRequest $request): bool
    {
        return $this->fxAuthorization->canUploadSignedFx($user, $request);
    }

    /**
     * Organization-classification data scope, identical to the rule DataScope
     * applies to list queries: NATIONAL_COMMITTEE → system-wide; BANKING_SECTOR →
     * own bank only; any other classification (or no organization) → deny.
     *
     * A null bank_id alone is NOT sufficient to be in scope — the list path and
     * the detail/mutation path must agree, otherwise a deny-all-list user could
     * still open or transition any request by ID (RBAC-004).
     */
    private function inScope(User $user, EngineRequest $request): bool
    {
        $scope = DataScope::forUser($user);

        if ($scope->systemWide) {
            return true;
        }

        if ($scope->ownBankId !== null) {
            return (int) $scope->ownBankId === (int) $request->bank_id;
        }

        return false;
    }
}

<?php

namespace App\Services\Customs;

use App\Enums\StageAccessLevel;
use App\Enums\StageSemanticRole;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\Workflow\StagePermissionResolver;
use App\Support\RoleCodes;

class FxConfirmationAuthorizationService
{
    public function __construct(private readonly StagePermissionResolver $resolver) {}

    public function resolveFxStage(EngineRequest $engineRequest): ?WorkflowStage
    {
        $code = (string) config('engine_hooks.fx_pdf_stage', 'FX_CONFIRM');

        return WorkflowStage::query()
            ->where('workflow_version_id', $engineRequest->workflow_version_id)
            ->where(function ($query) use ($code): void {
                $query->where('code', $code)
                    ->orWhere('semantic_role', StageSemanticRole::FX_CONFIRMATION);
            })
            ->first();
    }

    public function requestInScope(User $user, EngineRequest $engineRequest): bool
    {
        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return true;
        }

        return EngineRequest::query()
            ->whereKey($engineRequest->id)
            ->forUser($user)
            ->exists();
    }

    public function isAtOrPastFxStage(EngineRequest $engineRequest): bool
    {
        if ($engineRequest->relationLoaded('customsDeclaration') && $engineRequest->customsDeclaration !== null) {
            return true;
        }

        $fxStage = $this->resolveFxStage($engineRequest);
        if ($fxStage === null) {
            return false;
        }

        if ((int) $engineRequest->current_stage_id === (int) $fxStage->id) {
            return true;
        }

        return $engineRequest->history()
            ->where('to_stage_id', $fxStage->id)
            ->exists();
    }

    public function canUploadSignedFx(User $user, EngineRequest $engineRequest): bool
    {
        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return true;
        }

        if (! $this->requestInScope($user, $engineRequest)) {
            return false;
        }

        $fxStage = $this->resolveFxStage($engineRequest);
        if ($fxStage === null) {
            return false;
        }

        return $this->resolver->userCanAccessStage($user, $fxStage, StageAccessLevel::EXECUTE);
    }

    public function canDownloadArtifact(User $user, CustomsDeclaration $declaration): bool
    {
        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return true;
        }

        $declaration->loadMissing('engineRequest');
        $engineRequest = $declaration->engineRequest;
        if ($engineRequest === null) {
            return false;
        }

        if (! $this->requestInScope($user, $engineRequest)) {
            return false;
        }

        $fxStage = $this->resolveFxStage($engineRequest);
        if ($fxStage === null) {
            return false;
        }

        return $this->resolver->userCanAccessStage($user, $fxStage, StageAccessLevel::VIEW);
    }

    /**
     * @return array{visible: bool, can_upload_signed_fx: bool, can_download_declaration: bool, can_download_signed_fx: bool}
     */
    public function panelCapabilities(User $user, EngineRequest $engineRequest): array
    {
        $declaration = $engineRequest->relationLoaded('customsDeclaration')
            ? $engineRequest->customsDeclaration
            : null;

        $canDownload = $declaration !== null && $this->canDownloadArtifact($user, $declaration);

        return [
            'visible' => $this->isAtOrPastFxStage($engineRequest),
            'can_upload_signed_fx' => $this->canUploadSignedFx($user, $engineRequest),
            'can_download_declaration' => $canDownload,
            'can_download_signed_fx' => $canDownload && $declaration->hasSigned(),
        ];
    }
}

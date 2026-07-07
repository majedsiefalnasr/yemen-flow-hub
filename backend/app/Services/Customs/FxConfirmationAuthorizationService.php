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
    /**
     * Per-workflow_version_id FX-stage lookup cache. Bound as a container
     * singleton (see AppServiceProvider) so this survives across the per-row
     * app(FxConfirmationAuthorizationService::class) resolutions in
     * EngineRequestResource::toArray() during list serialization, eliminating
     * a WorkflowStage query per row for requests sharing a workflow version.
     * Keyed with array_key_exists (not isset) so a genuine "no FX stage"
     * result (null) is cached too, rather than re-queried every row.
     *
     * @var array<int, WorkflowStage|null>
     */
    private array $fxStageCache = [];

    /**
     * Per-(user_id, stage_id, access_level) stage-access cache. StagePermissionResolver
     * re-queries stage_permissions (plus the user's teams/roles) on every call; for a
     * list of requests sharing the same FX stage and acting user, this collapses those
     * repeated lookups to one per distinct combination.
     *
     * @var array<string, bool>
     */
    private array $stageAccessCache = [];

    public function __construct(private readonly StagePermissionResolver $resolver) {}

    public function resolveFxStage(EngineRequest $engineRequest): ?WorkflowStage
    {
        $workflowVersionId = $engineRequest->workflow_version_id;
        if (array_key_exists($workflowVersionId, $this->fxStageCache)) {
            return $this->fxStageCache[$workflowVersionId];
        }

        $code = (string) config('engine_hooks.fx_pdf_stage', 'FX_CONFIRM');

        $stage = WorkflowStage::query()
            ->where('workflow_version_id', $workflowVersionId)
            ->where(function ($query) use ($code): void {
                $query->where('code', $code)
                    ->orWhere('semantic_role', StageSemanticRole::FX_CONFIRMATION);
            })
            ->first();

        return $this->fxStageCache[$workflowVersionId] = $stage;
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

        return $this->cachedUserCanAccessStage($user, $fxStage, StageAccessLevel::EXECUTE);
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

        return $this->cachedUserCanAccessStage($user, $fxStage, StageAccessLevel::VIEW);
    }

    private function cachedUserCanAccessStage(User $user, WorkflowStage $stage, StageAccessLevel $required): bool
    {
        $cacheKey = $user->getKey().':'.$stage->getKey().':'.$required->value;
        if (array_key_exists($cacheKey, $this->stageAccessCache)) {
            return $this->stageAccessCache[$cacheKey];
        }

        return $this->stageAccessCache[$cacheKey] = $this->resolver->userCanAccessStage($user, $stage, $required);
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

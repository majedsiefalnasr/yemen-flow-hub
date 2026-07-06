<?php

namespace App\Support;

use App\Enums\OrganizationClassification;
use App\Enums\StageAccessLevel;
use App\Models\Organization;
use App\Models\StagePermission;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\DB;

class InitialStageExecutorGuard
{
    public static function organizationHasPublishedInitialExecuteGrants(Organization $organization): bool
    {
        return DB::table('stage_permissions')
            ->join('workflow_stages', 'workflow_stages.id', '=', 'stage_permissions.stage_id')
            ->join('workflow_versions', 'workflow_versions.id', '=', 'workflow_stages.workflow_version_id')
            ->where('workflow_versions.state', 'PUBLISHED')
            ->where('workflow_stages.is_initial', true)
            ->where('stage_permissions.access_level', StageAccessLevel::EXECUTE->value)
            ->where('stage_permissions.organization_id', $organization->id)
            ->exists();
    }

    public static function stageHasNonBankingInitialExecutors(WorkflowStage $stage): bool
    {
        if (! $stage->is_initial) {
            return false;
        }

        return StagePermission::query()
            ->where('stage_id', $stage->id)
            ->where('access_level', StageAccessLevel::EXECUTE)
            ->whereNotNull('organization_id')
            ->whereIn('organization_id', Organization::query()
                ->where('classification', '!=', OrganizationClassification::BANKING_SECTOR->value)
                ->pluck('id'))
            ->exists();
    }

    public static function isNonBankingInitialExecuteGrant(
        bool $isInitialStage,
        StageAccessLevel $accessLevel,
        ?int $organizationId,
    ): bool {
        if (! $isInitialStage || $accessLevel !== StageAccessLevel::EXECUTE || $organizationId === null) {
            return false;
        }

        $organization = Organization::query()->find($organizationId);

        return $organization !== null
            && $organization->classification !== OrganizationClassification::BANKING_SECTOR;
    }
}

<?php

namespace Tests\Support;

use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Support\Str;

/**
 * Minimal engine workflow seeding helpers for feature tests.
 *
 * Mirrors the workflow construction used in EngineDomainHooksTest::buildWorkflow()
 * (workflow definition -> published version -> stage(s)), trimmed to the columns
 * each helper actually needs.
 */
class EngineWorkflowFactory
{
    /**
     * Seed a published workflow whose current stage requires a claim, and return
     * an ACTIVE EngineRequest parked on that stage.
     */
    public static function seedRequestOnClaimStage(): EngineRequest
    {
        $def = WorkflowDefinition::create([
            'code' => 'CLAIM_WF_'.Str::random(8),
            'name' => 'Claim Test Workflow',
            'is_active' => true,
        ]);

        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'CLAIM_STAGE',
            'name' => 'Claim Stage',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'requires_claim' => true,
            'version' => 1,
        ]);

        $creator = User::factory()->create();

        return EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'ENG-CLAIM-'.Str::random(10),
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'version' => 1,
        ]);
    }

    /**
     * Extends seedRequestOnClaimStage()'s workflow with an outgoing transition from
     * the requires_claim stage, plus a user holding EXECUTE on that stage. Used by
     * EngineTransitionService::execute() guard tests, which need a real transition
     * to attempt (not just a parked request).
     *
     * @return array{request: EngineRequest, transitionId: int, executor: User}
     */
    public static function seedClaimStageWithTransition(): array
    {
        $def = WorkflowDefinition::create([
            'code' => 'CLAIM_WF_'.Str::random(8),
            'name' => 'Claim Test Workflow',
            'is_active' => true,
        ]);

        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $claimStage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'CLAIM_STAGE',
            'name' => 'Claim Stage',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'requires_claim' => true,
            'version' => 1,
        ]);

        $nextStage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'NEXT_STAGE',
            'name' => 'Next Stage',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => true,
            'version' => 1,
        ]);

        $executor = User::factory()->create();

        StagePermission::create([
            'stage_id' => $claimStage->id,
            'user_id' => $executor->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Exec',
            'version' => 1,
        ]);

        $action = WorkflowAction::create([
            'code' => 'ADVANCE',
            'name' => 'Advance',
            'kind' => 'APPROVE',
            'is_active' => true,
            'version' => 1,
        ]);

        $transition = WorkflowTransition::create([
            'workflow_version_id' => $version->id,
            'from_stage_id' => $claimStage->id,
            'to_stage_id' => $nextStage->id,
            'action_id' => $action->id,
            'requires_comment' => false,
            'version' => 1,
        ]);

        $request = EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $claimStage->id,
            'reference' => 'ENG-CLAIM-'.Str::random(10),
            'status' => 'ACTIVE',
            'created_by' => $executor->id,
            'version' => 1,
        ]);

        return [
            'request' => $request,
            'transitionId' => $transition->id,
            'executor' => $executor,
        ];
    }
}

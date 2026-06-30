<?php

namespace Tests\Support;

use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
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
}

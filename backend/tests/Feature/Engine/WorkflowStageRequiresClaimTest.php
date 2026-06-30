<?php

namespace Tests\Feature\Engine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class WorkflowStageRequiresClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_update_persists_requires_claim(): void
    {
        ['admin' => $admin, 'stage' => $stage] = EngineWorkflowFactory::draftStageForAdmin();

        $this->actingAs($admin)
            ->putJson("/api/v1/workflow-versions/{$stage->workflow_version_id}/stages/{$stage->id}", [
                'requires_claim' => true,
                'version' => $stage->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.requires_claim', true);

        $this->assertTrue((bool) $stage->fresh()->requires_claim);
    }
}

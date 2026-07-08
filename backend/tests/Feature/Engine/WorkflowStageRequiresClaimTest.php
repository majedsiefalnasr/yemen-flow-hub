<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class WorkflowStageRequiresClaimTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->seed(ScreenPermissionSeeder::class);
    }

    public function test_stage_update_persists_requires_claim(): void
    {
        ['admin' => $admin, 'stage' => $stage] = EngineWorkflowFactory::draftStageForAdmin();
        $admin = $this->assignGovernanceIdentity($admin, UserRole::CBY_ADMIN);

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

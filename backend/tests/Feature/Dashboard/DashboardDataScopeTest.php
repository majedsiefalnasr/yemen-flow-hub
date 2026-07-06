<?php

namespace Tests\Feature\Dashboard;

use App\Enums\OrganizationClassification;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Support\RoleCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class DashboardDataScopeTest extends TestCase
{
    use RefreshDatabase, AssignsGovernanceIdentity;

    private $versionId;
    private $stageId;
    private $supportStageId;
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();

        $definition = WorkflowDefinition::create(['name' => 'Test Workflow', 'code' => 'TEST']);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'version' => 1,
        ]);
        $stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'name' => 'Test Stage',
            'code' => 'TEST',
        ]);
        
        $supportStage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'name' => 'Support Stage',
            'code' => 'SUPPORT',
        ]);

        $this->versionId = $version->id;
        $this->stageId = $stage->id;
        $this->supportStageId = $supportStage->id;
        $this->userId = User::factory()->create()->id;
    }

    private function createRequest(array $attributes): EngineRequest
    {
        return EngineRequest::create(array_merge([
            'workflow_version_id' => $this->versionId,
            'current_stage_id' => $this->stageId,
            'created_by' => $this->userId,
            'version' => 1,
        ], $attributes));
    }

    public function test_banking_sector_user_sees_only_own_bank_data(): void
    {
        $org1 = Organization::factory()->create(['classification' => OrganizationClassification::BANKING_SECTOR]);
        $bank1 = Bank::factory()->create(['organization_id' => $org1->id]);
        $user1 = User::factory()->create(['organization_id' => $org1->id, 'bank_id' => $bank1->id]);
        $this->assignGovernanceIdentity($user1, UserRole::BANK_ADMIN);
        
        // Ensure classification is correct after identity assignment
        $user1->organization->update(['classification' => OrganizationClassification::BANKING_SECTOR]);

        $org2 = Organization::factory()->create(['classification' => OrganizationClassification::BANKING_SECTOR]);
        $bank2 = Bank::factory()->create(['organization_id' => $org2->id]);
        
        $this->createRequest(['bank_id' => $bank1->id, 'status' => 'ACTIVE', 'amount' => 100, 'currency' => 'USD', 'reference' => 'REF1']);
        $this->createRequest(['bank_id' => $bank2->id, 'status' => 'ACTIVE', 'amount' => 200, 'currency' => 'USD', 'reference' => 'REF2']);

        $response = $this->actingAs($user1)->getJson('/api/dashboard/stats');
        
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_national_committee_user_sees_all_data(): void
    {
        $org = Organization::factory()->create(['classification' => OrganizationClassification::NATIONAL_COMMITTEE]);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $this->assignGovernanceIdentity($user, UserRole::SUPPORT_COMMITTEE);
        
        // Ensure classification is correct
        $user->organization->update(['classification' => OrganizationClassification::NATIONAL_COMMITTEE]);

        $bank1 = Bank::factory()->create();
        $bank2 = Bank::factory()->create();
        
        $this->createRequest(['bank_id' => $bank1->id, 'status' => 'ACTIVE', 'amount' => 100, 'currency' => 'USD', 'reference' => 'REF1', 'current_stage_id' => $this->supportStageId]);
        $this->createRequest(['bank_id' => $bank2->id, 'status' => 'ACTIVE', 'amount' => 200, 'currency' => 'USD', 'reference' => 'REF2', 'current_stage_id' => $this->supportStageId]);

        // Support committee stats use 'waiting_for_claim', 'active_by_me', 'claimed_by_others'
        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');
        
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.waiting_for_claim'));
    }

    public function test_other_classification_user_sees_no_data(): void
    {
        $user = User::factory()->create();
        $this->assignGovernanceIdentity($user, UserRole::SUPPORT_COMMITTEE);
        
        // Change classification to OTHER
        $user->organization->update(['classification' => OrganizationClassification::OTHER]);

        $bank = Bank::factory()->create();
        $this->createRequest(['bank_id' => $bank->id, 'status' => 'ACTIVE', 'amount' => 100, 'currency' => 'USD', 'reference' => 'REF1', 'current_stage_id' => $this->supportStageId]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');
        
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.waiting_for_claim'));
    }

    public function test_null_bank_non_nc_user_sees_no_data(): void
    {
        // User with no organization (defaults to OTHER-equivalent in DataScope)
        $user = User::factory()->create();
        $this->assignGovernanceIdentity($user, UserRole::SUPPORT_COMMITTEE);
        
        // Remove organization
        $user->update(['organization_id' => null]);

        $bank = Bank::factory()->create();
        $this->createRequest(['bank_id' => $bank->id, 'status' => 'ACTIVE', 'amount' => 100, 'currency' => 'USD', 'reference' => 'REF1', 'current_stage_id' => $this->supportStageId]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');
        
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.waiting_for_claim'));
    }

    public function test_system_admin_preserves_system_wide_view(): void
    {
        $user = User::factory()->create();
        $this->assignGovernanceIdentity($user, UserRole::CBY_ADMIN);
        
        // Ensure classification is correct (system_admin is usually in system_administration org)
        // But the code explicitly handles isSystemAdmin() to be systemWide: true

        $bank1 = Bank::factory()->create();
        $bank2 = Bank::factory()->create();
        
        $this->createRequest(['bank_id' => $bank1->id, 'status' => 'ACTIVE', 'amount' => 100, 'currency' => 'USD', 'reference' => 'REF1']);
        $this->createRequest(['bank_id' => $bank2->id, 'status' => 'ACTIVE', 'amount' => 200, 'currency' => 'USD', 'reference' => 'REF2']);

        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');
        
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.total'));
    }
}

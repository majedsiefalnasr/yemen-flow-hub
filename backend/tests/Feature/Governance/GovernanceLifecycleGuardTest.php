<?php

namespace Tests\Feature\Governance;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceLifecycleGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_delete_role_referenced_by_published_workflow_is_blocked_and_audited(): void
    {
        $role = Role::query()->create([
            'organization_id' => Organization::query()->where('code', 'commercial_banks')->value('id'),
            'code' => 'guard_delete_role',
            'name' => 'Guard Delete Role',
            'is_active' => true,
        ]);
        $this->attachPublishedPermission($role);

        $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ROLE_REFERENCED_BY_PUBLISHED_WORKFLOW');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AUTHORIZATION_FAILURE',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
        ]);
    }

    public function test_bank_with_closed_history_can_suspend_but_not_delete(): void
    {
        $bank = Bank::query()->where('code', 'YBRD')->firstOrFail();
        $version = $this->createPublishedVersion();
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'closed_stage',
            'name' => 'Closed Stage',
            'is_initial' => true,
            'is_final' => true,
        ]);
        EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'ENG-CLOSED-1',
            'status' => 'COMPLETED',
            'bank_id' => $bank->id,
            'created_by' => $this->admin->id,
            'version' => 1,
        ]);

        $this->actingAs($this->admin)->postJson("/api/v1/banks/{$bank->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');

        $this->actingAs($this->admin)->deleteJson("/api/v1/banks/{$bank->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BANK_IN_USE');
    }

    public function test_user_with_only_authored_requests_can_deactivate(): void
    {
        $user = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();
        $version = $this->createPublishedVersion();
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'auth_stage',
            'name' => 'Auth Stage',
            'is_initial' => true,
            'is_final' => true,
        ]);
        EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'ENG-AUTH-ONLY',
            'status' => 'ACTIVE',
            'created_by' => $user->id,
            'bank_id' => $user->bank_id,
            'version' => 1,
        ]);

        $this->actingAs($this->admin)->postJson("/api/v1/users/{$user->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_governance_impact_endpoint_returns_payload(): void
    {
        $role = Role::query()->where('code', 'intake')->firstOrFail();
        $this->attachPublishedPermission($role);

        $this->actingAs($this->admin)->getJson('/api/v1/governance/impact?entity_type=role&entity_id='.$role->id)
            ->assertOk()
            ->assertJsonPath('data.entity_type', 'role')
            ->assertJsonPath('data.referenced_by_published', true);
    }

    private function attachPublishedPermission(Role $role): WorkflowStage
    {
        $org = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $definition = WorkflowDefinition::query()->create(['code' => 'guard-feature-'.uniqid(), 'name' => 'Guard Feature']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
        ]);
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'guard_stage',
            'name' => 'Guard Stage',
            'is_initial' => true,
            'is_final' => false,
        ]);
        StagePermission::query()->create([
            'stage_id' => $stage->id,
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Guard',
        ]);

        return $stage;
    }

    private function createPublishedVersion(): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'guard-ver-'.uniqid(), 'name' => 'Guard Version']);

        return WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
        ]);
    }
}

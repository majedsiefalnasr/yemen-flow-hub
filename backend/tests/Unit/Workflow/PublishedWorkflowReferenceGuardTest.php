<?php

namespace Tests\Unit\Workflow;

use App\Enums\GovernanceReferenceEntityType;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\PublishedWorkflowReferenceGuard;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedWorkflowReferenceGuardTest extends TestCase
{
    use RefreshDatabase;

    private PublishedWorkflowReferenceGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->guard = app(PublishedWorkflowReferenceGuard::class);
    }

    public function test_role_referenced_by_published_permissions(): void
    {
        $role = Role::query()->where('code', 'intake')->firstOrFail();
        $this->seedPublishedStageWithRole($role);

        $this->assertTrue(
            $this->guard->isReferencedByPublishedPermissions(GovernanceReferenceEntityType::ROLE, $role->id),
        );
    }

    public function test_would_break_executor_when_sole_holder(): void
    {
        $org = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $role = Role::query()->where('code', 'intake')->firstOrFail();
        $user = $this->firstUserWithRole(UserRole::DATA_ENTRY);
        $user->roles()->sync([$role->id]);

        $stage = $this->seedPublishedStageWithRole($role);

        $this->assertTrue(
            $this->guard->wouldLeaveStageWithoutExecutor(GovernanceReferenceEntityType::ROLE, $role->id),
        );

        $backupRole = Role::query()->create([
            'organization_id' => $org->id,
            'code' => 'backup_intake',
            'name' => 'Backup Intake',
            'is_active' => true,
        ]);
        $backup = User::query()->create([
            'name' => 'Backup User',
            'email' => 'backup-intake@test.local',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $backup->roles()->sync([$backupRole->id]);
        StagePermission::query()->create([
            'stage_id' => $stage->id,
            'organization_id' => $org->id,
            'role_id' => $backupRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Backup',
        ]);

        $this->assertFalse(
            $this->guard->wouldLeaveStageWithoutExecutor(GovernanceReferenceEntityType::ROLE, $role->id),
        );
    }

    public function test_draft_only_reference_is_not_published_block(): void
    {
        $role = Role::query()->create([
            'organization_id' => Organization::query()->where('code', 'commercial_banks')->value('id'),
            'code' => 'draft_only_role',
            'name' => 'Draft Only',
            'is_active' => true,
        ]);

        $definition = WorkflowDefinition::query()->create(['code' => 'draft-ref', 'name' => 'Draft Ref']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'draft_stage',
            'name' => 'Draft Stage',
            'is_initial' => true,
            'is_final' => false,
        ]);
        StagePermission::query()->create([
            'stage_id' => $stage->id,
            'role_id' => $role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Draft',
        ]);

        $this->assertFalse(
            $this->guard->isReferencedByPublishedPermissions(GovernanceReferenceEntityType::ROLE, $role->id),
        );
        $this->assertTrue(
            $this->guard->referencedByDraft(GovernanceReferenceEntityType::ROLE, $role->id),
        );
    }

    public function test_impact_payload_shape(): void
    {
        $role = Role::query()->where('code', 'intake')->firstOrFail();
        $this->seedPublishedStageWithRole($role);

        $impact = $this->guard->impact(GovernanceReferenceEntityType::ROLE, $role->id);

        $this->assertSame('role', $impact['entity_type']);
        $this->assertSame($role->id, $impact['entity_id']);
        $this->assertTrue($impact['referenced_by_published']);
        $this->assertArrayHasKey('affected', $impact);
        $this->assertNotEmpty($impact['affected']);
        $this->assertArrayHasKey('workflow_definition', $impact['affected'][0]);
        $this->assertArrayHasKey('stage', $impact['affected'][0]);
    }

    private function seedPublishedStageWithRole(Role $role): WorkflowStage
    {
        $org = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $definition = WorkflowDefinition::query()->create(['code' => 'guard-'.uniqid(), 'name' => 'Guard WF']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
        ]);
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'review',
            'name' => 'Review',
            'is_initial' => true,
            'is_final' => false,
        ]);
        StagePermission::query()->create([
            'stage_id' => $stage->id,
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Reviewers',
        ]);

        return $stage;
    }
}

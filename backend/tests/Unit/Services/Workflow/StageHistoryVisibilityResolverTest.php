<?php

namespace Tests\Unit\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\StageHistoryVisibility;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Services\Workflow\StageHistoryVisibilityResolver;
use App\Services\Workflow\StagePermissionResolver;
use App\Support\RoleCodes;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageHistoryVisibilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private StageHistoryVisibilityResolver $resolver;

    private User $userWithAccess;

    private User $userWithoutAccess;

    private User $systemAdmin;

    private WorkflowStage $restrictedStage;

    private WorkflowHistoryEntry $entryByOtherUser;

    private WorkflowHistoryEntry $entryByUserWithoutAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->resolver = new StageHistoryVisibilityResolver(new StagePermissionResolver);

        $org = Organization::query()->where('code', 'national_committee')->first();
        $role = Role::query()->where('code', 'support')->first();

        $this->userWithAccess = User::create([
            'name' => 'Has Access', 'email' => 'has-access@test.cby',
            'password' => bcrypt('password'), 'organization_id' => $org->id, 'is_active' => true,
        ]);
        $this->userWithAccess->roles()->attach($role);

        $this->userWithoutAccess = User::create([
            'name' => 'No Access', 'email' => 'no-access@test.cby',
            'password' => bcrypt('password'), 'organization_id' => $org->id, 'is_active' => true,
        ]);

        $otherRole = Role::query()->where('code', 'intake')->first();
        $this->userWithoutAccess->roles()->attach($otherRole);

        $this->systemAdmin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.cby',
            'password' => bcrypt('password'), 'organization_id' => $org->id, 'is_active' => true,
        ]);
        $adminRole = Role::query()->where('code', RoleCodes::SYSTEM_ADMIN)->first();
        $this->systemAdmin->roles()->attach($adminRole);

        $definition = WorkflowDefinition::create(['code' => 'test_visibility', 'name' => 'Test']);
        $version = $definition->versions()->create([
            'version_number' => 1, 'state' => WorkflowVersionState::PUBLISHED, 'published_at' => now(),
        ])->refresh();

        $entryStage = $version->stages()->create(['code' => 'ENTRY', 'name' => 'Entry', 'is_initial' => true, 'sort_order' => 1]);
        $this->restrictedStage = $version->stages()->create(['code' => 'RESTRICTED', 'name' => 'Restricted', 'is_final' => true, 'sort_order' => 2]);

        StagePermission::create([
            'stage_id' => $this->restrictedStage->id,
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Support Access',
            'version' => 1,
        ]);

        $action = WorkflowAction::create(['code' => 'APPROVE_TEST', 'name' => 'Approve', 'kind' => 'APPROVE', 'is_active' => true]);
        WorkflowTransition::create([
            'workflow_version_id' => $version->id,
            'from_stage_id' => $entryStage->id,
            'to_stage_id' => $this->restrictedStage->id,
            'action_id' => $action->id,
        ]);

        $requestRow = EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $this->restrictedStage->id,
            'status' => 'ACTIVE',
            'reference' => 'TEST-001',
            'created_by' => $this->userWithAccess->id,
            'data' => [],
            'version' => 1,
        ]);

        $this->entryByOtherUser = WorkflowHistoryEntry::create([
            'request_id' => $requestRow->id,
            'from_stage_id' => $entryStage->id,
            'to_stage_id' => $this->restrictedStage->id,
            'action_code' => 'APPROVE_TEST',
            'performed_by' => $this->userWithAccess->id,
            'comments' => 'secret comment',
            'created_at' => now(),
        ]);

        $this->entryByUserWithoutAccess = WorkflowHistoryEntry::create([
            'request_id' => $requestRow->id,
            'from_stage_id' => $entryStage->id,
            'to_stage_id' => $this->restrictedStage->id,
            'action_code' => 'APPROVE_TEST',
            'performed_by' => $this->userWithoutAccess->id,
            'comments' => 'my own secret comment',
            'created_at' => now(),
        ]);
    }

    public function test_user_with_stage_access_sees_full_visibility(): void
    {
        $this->assertSame(
            StageHistoryVisibility::FULL,
            $this->resolver->visibilityFor($this->userWithAccess, $this->entryByOtherUser),
        );
    }

    public function test_actor_without_stage_access_sees_sanitized_visibility_on_own_entry(): void
    {
        $this->assertSame(
            StageHistoryVisibility::SANITIZED,
            $this->resolver->visibilityFor($this->userWithoutAccess, $this->entryByUserWithoutAccess),
        );
    }

    public function test_non_actor_without_stage_access_sees_hidden_visibility(): void
    {
        $this->assertSame(
            StageHistoryVisibility::HIDDEN,
            $this->resolver->visibilityFor($this->userWithoutAccess, $this->entryByOtherUser),
        );
    }

    public function test_system_admin_always_sees_full_visibility(): void
    {
        $this->assertSame(
            StageHistoryVisibility::FULL,
            $this->resolver->visibilityFor($this->systemAdmin, $this->entryByOtherUser),
        );
    }
}

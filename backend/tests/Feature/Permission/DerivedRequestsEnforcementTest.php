<?php

namespace Tests\Feature\Permission;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DerivedRequestsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernanceSeeder::class);
        $this->seed(ScreenPermissionSeeder::class);
    }

    public function test_requests_capability_comes_from_workflow_not_screen_permissions_table(): void
    {
        $org = Organization::where('code', 'commercial_banks')->firstOrFail();
        $role = Role::create([
            'organization_id' => $org->id,
            'code' => 'derived_test_role',
            'name' => 'Derived Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'role' => UserRole::DATA_ENTRY,
        ]);
        $user->roles()->attach($role->id);

        // Manually seed a stale/wrong static grant directly into screen_permissions —
        // this must be IGNORED once requests is workflow-derived.
        $screenId = DB::table('screens')->where('key', 'requests')->value('id');
        DB::table('screen_permissions')->insert([
            'role_id' => $role->id,
            'screen_id' => $screenId,
            'capability' => 'MANAGE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set up a published workflow granting only VIEW (not EXECUTE) on the initial stage.
        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'enforce_test_wf', 'name' => 'Enforce Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $versionId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED->value, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId, 'code' => 'intake', 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stageId, 'role_id' => $role->id, 'access_level' => 'VIEW',
            'display_label' => $role->name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(PermissionService::class);
        $sp = $service->screenPermissionsForGovernanceRole($role->id);

        // The stale MANAGE grant in screen_permissions must NOT appear.
        $this->assertArrayHasKey('requests', $sp);
        $this->assertNotContains('MANAGE', $sp['requests']);
        // Workflow says VIEW only (not EXECUTE), so CREATE/UPDATE must be absent.
        $this->assertContains('VIEW', $sp['requests']);
        $this->assertNotContains('CREATE', $sp['requests']);
        $this->assertNotContains('UPDATE', $sp['requests']);
    }

    public function test_publishing_new_workflow_version_changes_effective_requests_capability(): void
    {
        $org = Organization::where('code', 'commercial_banks')->firstOrFail();
        $role = Role::create([
            'organization_id' => $org->id,
            'code' => 'publish_test_role',
            'name' => 'Publish Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'publish_test_wf', 'name' => 'Publish Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $v1Id = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED->value, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stage1Id = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $v1Id, 'code' => 'intake', 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stage1Id, 'role_id' => $role->id, 'access_level' => 'EXECUTE',
            'display_label' => $role->name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(PermissionService::class);
        $before = $service->screenPermissionsForGovernanceRole($role->id);
        $this->assertContains('CREATE', $before['requests']);

        // Simulate publishing a new version that does NOT grant this role EXECUTE.
        DB::table('workflow_versions')->where('id', $v1Id)->update(['state' => WorkflowVersionState::ARCHIVED->value]);
        $v2Id = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId, 'version_number' => 2,
            'state' => WorkflowVersionState::PUBLISHED->value, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $v2Id, 'code' => 'intake', 'name' => 'Intake', 'is_initial' => true,
            'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // No stage_permissions row for $role on the v2 stage → no access.

        $service->clearScreenPermissionCache($role->id);
        $after = $service->screenPermissionsForGovernanceRole($role->id);
        $this->assertNotContains('CREATE', $after['requests'] ?? []);
    }

    public function test_put_screen_permissions_rejects_requests_key(): void
    {
        $org = Organization::where('code', 'system_administration')->firstOrFail();
        $adminRole = Role::where('code', 'system_admin')->firstOrFail();

        $admin = User::factory()->create([
            'organization_id' => $org->id,
            'role' => UserRole::CBY_ADMIN,
        ]);
        $admin->roles()->attach($adminRole->id);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $targetRole = Role::create([
            'organization_id' => $bankOrg->id,
            'code' => 'target_reject_test',
            'name' => 'Target Reject Test',
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/roles/{$targetRole->id}/screen-permissions", [
                'grants' => ['requests' => ['VIEW']],
            ])
            ->assertStatus(422);
    }
}

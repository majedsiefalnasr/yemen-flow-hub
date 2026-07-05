<?php

namespace Tests\Unit\Services;

use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Services\Authorization\PermissionService;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceDerivedRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
    }

    public function test_derives_capabilities_for_multiple_roles_from_published_workflow(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $roleA = Role::where('code', 'intake')->firstOrFail();
        $roleB = Role::where('code', 'internal_reviewer')->firstOrFail();

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'code' => 'test_wf_'.uniqid(),
            'name' => 'Test Workflow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initialStageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId,
            'code' => 'intake',
            'name' => 'Intake',
            'is_initial' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondStageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId,
            'code' => 'review',
            'name' => 'Review',
            'is_initial' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stage_permissions')->insert([
            ['stage_id' => $initialStageId, 'role_id' => $roleA->id, 'access_level' => 'EXECUTE', 'display_label' => $roleA->name, 'created_at' => now(), 'updated_at' => now()],
            ['stage_id' => $secondStageId, 'role_id' => $roleB->id, 'access_level' => 'VIEW', 'display_label' => $roleB->name, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(PermissionService::class);
        $result = $service->derivedRequestsCapabilities([$roleA->id, $roleB->id]);

        // Role A (intake): EXECUTE on the initial stage → view=true, add=true, edit=true
        $this->assertTrue($result[$roleA->id]['view']);
        $this->assertTrue($result[$roleA->id]['add']);
        $this->assertTrue($result[$roleA->id]['edit']);

        // Role B (internal_reviewer): VIEW only on a non-initial stage → view=true, add=false, edit=false
        $this->assertTrue($result[$roleB->id]['view']);
        $this->assertFalse($result[$roleB->id]['add']);
        $this->assertFalse($result[$roleB->id]['edit']);
    }

    public function test_role_with_no_assignments_gets_all_false(): void
    {
        $roleC = Role::where('code', 'fx_swift')->firstOrFail();

        $service = app(PermissionService::class);
        $result = $service->derivedRequestsCapabilities([$roleC->id]);

        $this->assertFalse($result[$roleC->id]['view']);
        $this->assertFalse($result[$roleC->id]['add']);
        $this->assertFalse($result[$roleC->id]['edit']);
    }

    public function test_org_only_stage_permission_row_grants_requests_access_to_roles_in_that_org(): void
    {
        $orgId = DB::table('organizations')->insertGetId(['code' => 'ORG1', 'name' => 'Org One', 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->insertGetId([
            'organization_id' => $orgId,
            'code' => 'reviewer',
            'name' => 'Reviewer',
            'is_system' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $definitionId = DB::table('workflow_definitions')->insertGetId(['code' => 'org-only', 'name' => 'Org Only Flow', 'created_at' => now(), 'updated_at' => now()]);
        $versionId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionId,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionId,
            'code' => 'intake',
            'name' => 'Intake',
            'is_initial' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stageId,
            'organization_id' => $orgId,
            'team_id' => null,
            'role_id' => null,
            'access_level' => 'VIEW',
            'display_label' => 'Org-wide',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(PermissionService::class);
        $result = $service->derivedRequestsCapabilities([$roleId]);

        $this->assertTrue($result[$roleId]['view']);
    }

    public function test_role_matching_only_second_definitions_stage_still_gets_capabilities(): void
    {
        $orgId = DB::table('organizations')->insertGetId(['code' => 'ORG2', 'name' => 'Org Two', 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->insertGetId([
            'organization_id' => $orgId,
            'code' => 'reviewer-2',
            'name' => 'Reviewer Two',
            'is_system' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $definitionAId = DB::table('workflow_definitions')->insertGetId(['code' => 'def-a', 'name' => 'Definition A', 'created_at' => now(), 'updated_at' => now()]);
        $versionAId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionAId,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionAId,
            'code' => 'stage-a',
            'name' => 'Stage A',
            'is_initial' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $definitionBId = DB::table('workflow_definitions')->insertGetId(['code' => 'def-b', 'name' => 'Definition B', 'created_at' => now(), 'updated_at' => now()]);
        $versionBId = DB::table('workflow_versions')->insertGetId([
            'workflow_definition_id' => $definitionBId,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stageBId = DB::table('workflow_stages')->insertGetId([
            'workflow_version_id' => $versionBId,
            'code' => 'stage-b',
            'name' => 'Stage B',
            'is_initial' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stage_permissions')->insert([
            'stage_id' => $stageBId,
            'organization_id' => $orgId,
            'team_id' => null,
            'role_id' => $roleId,
            'access_level' => 'EXECUTE',
            'display_label' => 'B reviewers',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(PermissionService::class);
        $result = $service->derivedRequestsCapabilities([$roleId]);

        $this->assertTrue($result[$roleId]['add']);
    }

    public function test_role_with_no_matching_row_across_multiple_definitions_gets_all_false(): void
    {
        $orgId = DB::table('organizations')->insertGetId(['code' => 'ORG3', 'name' => 'Org Three', 'created_at' => now(), 'updated_at' => now()]);
        $roleId = DB::table('roles')->insertGetId([
            'organization_id' => $orgId,
            'code' => 'no-access',
            'name' => 'No Access',
            'is_system' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['def-c', 'def-d'] as $code) {
            $definitionId = DB::table('workflow_definitions')->insertGetId(['code' => $code, 'name' => $code, 'created_at' => now(), 'updated_at' => now()]);
            $versionId = DB::table('workflow_versions')->insertGetId([
                'workflow_definition_id' => $definitionId,
                'version_number' => 1,
                'state' => 'PUBLISHED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('workflow_stages')->insertGetId([
                'workflow_version_id' => $versionId,
                'code' => $code.'-stage',
                'name' => $code.' Stage',
                'is_initial' => true,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $service = app(PermissionService::class);
        $result = $service->derivedRequestsCapabilities([$roleId]);

        $this->assertFalse($result[$roleId]['view']);
        $this->assertFalse($result[$roleId]['add']);
        $this->assertFalse($result[$roleId]['edit']);
    }
}

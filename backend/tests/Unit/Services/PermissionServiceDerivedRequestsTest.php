<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
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
            'code' => 'test_wf_' . uniqid(),
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
}

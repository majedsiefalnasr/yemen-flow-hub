<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private User $auditor;

    private User $unauthorized;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private WorkflowStage $nextStage;

    private WorkflowTransition $submitTransition;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $cbyOrg = Organization::where('code', 'national_committee')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $supportRole = Role::where('code', 'support')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();
        $supportTeam = Team::where('code', 'support')->firstOrFail();

        $this->bank = Bank::create([
            'name' => 'Audit Bank',
            'code' => 'ADB',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@audit.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->auditor = User::create([
            'name' => 'Auditor',
            'email' => 'auditor@audit.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::CBY_ADMIN,
            'bank_id' => null,
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $this->auditor->teams()->attach($supportTeam);
        $this->auditor->roles()->attach($supportRole);

        $this->unauthorized = User::create([
            'name' => 'Unauth',
            'email' => 'unauth@audit.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->unauthorized->teams()->attach($entryTeam);
        $this->unauthorized->roles()->attach($entryRole);

        $def = WorkflowDefinition::create(['code' => 'AUDIT_WF', 'name' => 'Audit WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->initialStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        $this->nextStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'REVIEW',
            'name' => 'Review',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => false,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Entry',
            'version' => 1,
        ]);

        // VIEW permission on the initial stage so executor can access request history.
        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Entry View',
            'version' => 1,
        ]);

        // VIEW permission on the next stage so history can be accessed after transition.
        StagePermission::create([
            'stage_id' => $this->nextStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Review View',
            'version' => 1,
        ]);

        $action = WorkflowAction::create([
            'code' => 'AUDIT_SUBMIT',
            'name' => 'Submit',
            'kind' => 'DRAFT',
            'is_active' => true,
            'version' => 1,
        ]);

        $this->submitTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->initialStage->id,
            'to_stage_id' => $this->nextStage->id,
            'action_id' => $action->id,
            'requires_comment' => false,
            'version' => 1,
        ]);
    }

    private function makeRequest(): EngineRequest
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'data' => ['amount' => 5000, 'currency' => 'USD'],
        ]);
        $response->assertCreated();

        return EngineRequest::findOrFail($response->json('data.id'));
    }

    public function test_audit_log_entry_created_on_request_creation(): void
    {
        $request = $this->makeRequest();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'REQUEST_CREATED',
            'subject_id' => $request->id,
        ]);
    }

    public function test_audit_log_entry_created_on_workflow_transition(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            ['transition_id' => $this->submitTransition->id, 'data' => [], 'version' => $request->version]
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $request->id,
            'action' => 'STATUS_TRANSITION',
        ]);
    }

    public function test_audit_log_list_endpoint_accessible_to_cbyadmin(): void
    {
        $this->makeRequest();

        $this->actingAs($this->auditor)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_audit_log_filtered_by_request(): void
    {
        $request = $this->makeRequest();

        // Execute a transition so that an audit log entry with workflow_instance_id = request->id is created.
        $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            ['transition_id' => $this->submitTransition->id, 'data' => [], 'version' => $request->version]
        )->assertOk();

        $this->actingAs($this->auditor)
            ->getJson("/api/v1/audit-logs?request={$request->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', function ($total) {
                return $total >= 1;
            });
    }

    public function test_unauthorized_user_cannot_access_audit_logs(): void
    {
        // DATA_ENTRY role lacks audit.view permission
        $this->actingAs($this->unauthorized)
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    public function test_history_endpoint_returns_ordered_transitions(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            ['transition_id' => $this->submitTransition->id, 'data' => [], 'version' => $request->version]
        )->assertOk();

        $response = $this->actingAs($this->executor)
            ->getJson("/api/v1/engine-requests/{$request->id}/history");

        $response->assertOk();
        $history = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($history));
        $this->assertEquals('CREATE', $history[0]['action_code']);
    }
}

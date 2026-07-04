<?php

namespace Tests\Feature\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\FieldDefinition;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowVersionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_create_definition_persists_and_auto_creates_first_draft_version(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/workflow-definitions', [
            'code' => 'import_financing',
            'name' => 'Import Financing',
            'description' => 'Standard import financing flow',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'import_financing')
            ->assertJsonPath('data.versions.0.version_number', 1)
            ->assertJsonPath('data.versions.0.state', 'DRAFT');

        $this->assertDatabaseHas('workflow_definitions', ['code' => 'import_financing']);
        $this->assertDatabaseHas('workflow_versions', [
            'workflow_definition_id' => $response->json('data.id'),
            'version_number' => 1,
            'state' => 'DRAFT',
        ]);
    }

    public function test_creating_definition_audits_the_action(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/workflow-definitions', [
            'code' => 'audited_flow',
            'name' => 'Audited Flow',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'GOVERNANCE_CREATED',
            'subject_type' => WorkflowDefinition::class,
            'subject_id' => $response->json('data.id'),
        ]);
    }

    public function test_duplicate_definition_code_is_rejected(): void
    {
        WorkflowDefinition::query()->create(['code' => 'dupe', 'name' => 'Dupe']);

        $this->actingAs($this->admin)->postJson('/api/v1/workflow-definitions', [
            'code' => 'dupe',
            'name' => 'Other',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_draft_version_accepts_edits(): void
    {
        $version = $this->draftVersion();

        $this->actingAs($this->admin)->putJson("/api/v1/workflow-versions/{$version->id}", [
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.state', 'DRAFT');
    }

    public function test_published_version_rejects_edits(): void
    {
        $version = $this->draftVersion();
        $version->update(['state' => WorkflowVersionState::PUBLISHED]);

        // Policy blocks mutation of PUBLISHED versions before the service runs (HTTP 403).
        $this->actingAs($this->admin)->putJson("/api/v1/workflow-versions/{$version->id}", [
            'version' => $version->fresh()->version,
        ])->assertForbidden();
    }

    public function test_archived_version_rejects_edits(): void
    {
        $version = $this->draftVersion();
        $version->update(['state' => WorkflowVersionState::ARCHIVED]);

        $this->actingAs($this->admin)->putJson("/api/v1/workflow-versions/{$version->id}", [
            'version' => $version->fresh()->version,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_clone_published_version_produces_independent_draft_and_original_unchanged(): void
    {
        $version = $this->draftVersion();
        $version->update(['state' => WorkflowVersionState::PUBLISHED, 'published_at' => now()]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/workflow-versions/{$version->id}/clone")
            ->assertCreated()
            ->assertJsonPath('data.state', 'DRAFT')
            ->assertJsonPath('data.version_number', 2);

        $this->assertNotSame($version->id, $response->json('data.id'));

        // Original is untouched.
        $this->assertDatabaseHas('workflow_versions', [
            'id' => $version->id,
            'state' => 'PUBLISHED',
            'version_number' => 1,
        ]);
        $this->assertDatabaseHas('workflow_versions', [
            'id' => $response->json('data.id'),
            'state' => 'DRAFT',
            'version_number' => 2,
        ]);
    }

    public function test_clone_deep_copies_stages_transitions_permissions_and_fields(): void
    {
        $version = $this->validDraftVersion();
        $intake = $version->stages()->where('code', 'intake')->firstOrFail();
        $group = $version->fieldGroups()->create(['name' => 'main', 'label' => 'Main', 'sort_order' => 0]);
        $field = FieldDefinition::query()->create([
            'workflow_version_id' => $version->id,
            'field_group_id' => $group->id,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => 'NUMBER',
            'sort_order' => 0,
        ]);
        $intake->stageFieldRules()->create([
            'field_id' => $field->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_required' => true,
        ]);
        $version->update(['state' => WorkflowVersionState::PUBLISHED, 'published_at' => now()]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/workflow-versions/{$version->id}/clone")
            ->assertCreated();
        $cloneId = $response->json('data.id');

        $this->assertDatabaseCount('workflow_stages', 4);
        $this->assertDatabaseCount('workflow_transitions', 2);
        $this->assertDatabaseCount('stage_permissions', 2);
        $this->assertDatabaseCount('field_groups', 2);
        $this->assertDatabaseCount('field_definitions', 2);
        $this->assertDatabaseCount('stage_field_rules', 2);

        $cloneIntake = WorkflowStage::query()->where('workflow_version_id', $cloneId)->where('code', 'intake')->firstOrFail();
        $this->assertNotSame($intake->id, $cloneIntake->id);
        $this->assertDatabaseHas('stage_permissions', ['stage_id' => $cloneIntake->id, 'display_label' => 'Reviewers']);
        $this->assertDatabaseHas('field_definitions', ['workflow_version_id' => $cloneId, 'key' => 'amount']);
        $this->assertDatabaseHas('stage_field_rules', ['stage_id' => $cloneIntake->id]);

        // Original untouched.
        $this->assertDatabaseHas('workflow_stages', ['id' => $intake->id, 'workflow_version_id' => $version->id]);
    }

    public function test_cloning_a_draft_version_is_rejected(): void
    {
        $version = $this->draftVersion();

        $this->actingAs($this->admin)->postJson("/api/v1/workflow-versions/{$version->id}/clone")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_duplicate_version_number_is_rejected_by_unique_constraint(): void
    {
        $version = $this->draftVersion();

        $this->expectException(QueryException::class);
        WorkflowVersion::query()->create([
            'workflow_definition_id' => $version->workflow_definition_id,
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);
    }

    public function test_publish_then_archive_lifecycle(): void
    {
        $version = $this->validDraftVersion();

        $this->actingAs($this->admin)->postJson("/api/v1/workflow-versions/{$version->id}/publish", [
            'version' => $version->version,
        ])->assertOk()->assertJsonPath('data.state', 'PUBLISHED');

        $this->actingAs($this->admin)->postJson("/api/v1/workflow-versions/{$version->id}/archive", [
            'version' => $version->fresh()->version,
        ])->assertOk()->assertJsonPath('data.state', 'ARCHIVED');
    }

    public function test_version_update_rejects_a_stale_version(): void
    {
        $version = $this->draftVersion();

        $this->actingAs($this->admin)->putJson("/api/v1/workflow-versions/{$version->id}", [
            'version' => 999,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_non_admin_cannot_access_workflow_designer(): void
    {
        $this->actingAs($this->nonAdmin)->getJson('/api/v1/workflow-definitions')->assertForbidden();
        $this->actingAs($this->nonAdmin)->postJson('/api/v1/workflow-definitions', [
            'code' => 'blocked',
            'name' => 'Blocked',
        ])->assertForbidden();
    }

    // G10: PUBLISHED version immutability — policy-layer guard tests

    public function test_update_endpoint_returns_403_for_published_version(): void
    {
        $version = $this->draftVersion();
        $version->update(['state' => WorkflowVersionState::PUBLISHED]);

        $this->actingAs($this->admin)->putJson("/api/v1/workflow-versions/{$version->id}", [
            'version' => $version->fresh()->version,
        ])->assertForbidden();
    }

    public function test_clone_of_published_version_is_allowed(): void
    {
        $version = $this->draftVersion();
        $version->update(['state' => WorkflowVersionState::PUBLISHED, 'published_at' => now()]);

        $cloneResp = $this->actingAs($this->admin)->postJson("/api/v1/workflow-versions/{$version->id}/clone");
        $cloneResp->assertCreated();
        $this->assertEquals('DRAFT', $cloneResp->json('data.state'));
    }

    public function test_draft_version_update_is_still_allowed(): void
    {
        $version = $this->draftVersion();

        $this->actingAs($this->admin)->putJson("/api/v1/workflow-versions/{$version->id}", [
            'version' => $version->version,
        ])->assertOk()
            ->assertJsonPath('data.state', 'DRAFT');
    }

    private function draftVersion(): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'flow_'.uniqid(), 'name' => 'Flow']);

        return $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();
    }

    /**
     * A minimal version that passes validate-before-publish: one initial non-final
     * stage with an EXECUTE permission + an outgoing transition → one final stage.
     */
    private function validDraftVersion(): WorkflowVersion
    {
        $version = $this->draftVersion();
        $org = Organization::query()->firstOrFail();
        $role = Role::query()->where('organization_id', $org->id)->firstOrFail();

        $intake = $version->stages()->create(['code' => 'intake', 'name' => 'Intake', 'is_initial' => true, 'sort_order' => 0]);
        $done = $version->stages()->create(['code' => 'done', 'name' => 'Done', 'is_final' => true, 'sort_order' => 1]);
        $intake->stagePermissions()->create([
            'organization_id' => $org->id,
            'role_id' => $role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Reviewers',
        ]);
        $approve = WorkflowAction::query()->create(['code' => 'APPROVE_'.uniqid(), 'name' => 'Approve', 'kind' => 'APPROVE']);
        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $approve->id,
            'to_stage_id' => $done->id,
        ]);

        return $version->refresh();
    }
}

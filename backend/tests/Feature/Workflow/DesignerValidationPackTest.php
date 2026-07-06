<?php

namespace Tests\Feature\Workflow;

use App\Enums\FinalOutcome;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowActionKind;
use App\Enums\WorkflowTransitionType;
use App\Enums\WorkflowVersionState;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowGraphService;
use App\Services\Workflow\WorkflowVersionValidator;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignerValidationPackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $org;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->org = Organization::query()->firstOrFail();
        $this->role = Role::query()->where('organization_id', $this->org->id)->firstOrFail();
    }

    private function validDraft(): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'flow_'.uniqid(), 'name' => 'Flow']);
        $version = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();

        $intake = $version->stages()->create(['code' => 'intake', 'name' => 'Intake', 'is_initial' => true, 'sort_order' => 0]);
        $done = $version->stages()->create([
            'code' => 'done',
            'name' => 'Done',
            'is_final' => true,
            'final_outcome' => FinalOutcome::COMPLETED,
            'sort_order' => 1,
        ]);

        $intake->stagePermissions()->create([
            'organization_id' => $this->org->id,
            'role_id' => $this->role->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Reviewers',
        ]);

        $approve = WorkflowAction::query()->create(['code' => 'APPROVE_'.uniqid(), 'name' => 'Approve', 'kind' => WorkflowActionKind::APPROVE]);
        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $approve->id,
            'to_stage_id' => $done->id,
            'is_default_submit' => true,
            'transition_type' => WorkflowTransitionType::FORWARD,
        ]);

        return $version->refresh();
    }

    public function test_validator_flags_initial_submit_ambiguity(): void
    {
        $version = $this->validDraft();
        $intake = $version->stages()->where('is_initial', true)->firstOrFail();
        $done = $version->stages()->where('is_final', true)->firstOrFail();
        $action = WorkflowAction::query()->create(['code' => 'SUBMIT2_'.uniqid(), 'name' => 'Submit 2', 'kind' => WorkflowActionKind::APPROVE]);

        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $action->id,
            'to_stage_id' => $done->id,
            'transition_type' => WorkflowTransitionType::FORWARD,
        ]);
        $version->transitions()->update(['is_default_submit' => false]);

        $codes = collect(app(WorkflowVersionValidator::class)->validate($version->fresh()))->pluck('code');

        $this->assertContains('INITIAL_SUBMIT_AMBIGUOUS', $codes);
    }

    public function test_validator_flags_unreachable_stage(): void
    {
        $version = $this->validDraft();
        $version->stages()->create(['code' => 'island', 'name' => 'Island', 'sort_order' => 2]);

        $codes = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');

        $this->assertContains('STAGE_UNREACHABLE', $codes);
    }

    public function test_validator_flags_final_stage_with_outgoing_transition(): void
    {
        $version = $this->validDraft();
        $intake = $version->stages()->where('is_initial', true)->firstOrFail();
        $done = $version->stages()->where('is_final', true)->firstOrFail();
        $action = WorkflowAction::query()->create(['code' => 'REOPEN_'.uniqid(), 'name' => 'Reopen', 'kind' => WorkflowActionKind::CUSTOM]);

        $version->transitions()->create([
            'from_stage_id' => $done->id,
            'action_id' => $action->id,
            'to_stage_id' => $intake->id,
            'is_self_loop' => false,
            'transition_type' => WorkflowTransitionType::RETURN,
        ]);

        $codes = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');

        $this->assertContains('FINAL_STAGE_HAS_OUTGOING', $codes);
    }

    public function test_validator_flags_unintentional_self_loop(): void
    {
        $version = $this->validDraft();
        $intake = $version->stages()->where('is_initial', true)->firstOrFail();
        $action = WorkflowAction::query()->create(['code' => 'NOTE_'.uniqid(), 'name' => 'Note', 'kind' => WorkflowActionKind::INFO]);

        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $action->id,
            'to_stage_id' => $intake->id,
            'is_self_loop' => false,
            'transition_type' => WorkflowTransitionType::FORWARD,
        ]);

        $codes = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');

        $this->assertContains('UNINTENTIONAL_SELF_LOOP', $codes);
    }

    public function test_cannot_delete_published_version(): void
    {
        $version = $this->validDraft();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/publish", ['version' => $version->version])
            ->assertOk();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$version->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PUBLISHED_NOT_DELETABLE');
    }

    public function test_definition_rename_persists_and_code_is_immutable(): void
    {
        $version = $this->validDraft();
        $definition = WorkflowDefinition::query()->findOrFail($version->workflow_definition_id);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-definitions/{$definition->id}", [
                'name' => 'Renamed Flow',
                'description' => 'Updated description',
                'version' => $definition->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Flow');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-definitions/{$definition->id}", [
                'code' => 'new_code',
                'version' => $definition->fresh()->version,
            ])
            ->assertStatus(422);
    }

    public function test_clone_from_archived_version(): void
    {
        $version = $this->validDraft();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/publish", ['version' => $version->version])
            ->assertOk();

        $published = $version->fresh();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$published->id}/archive", [
                'version' => $published->version,
                'reason' => 'retire',
            ])
            ->assertOk();

        $archived = $published->fresh();
        $this->assertSame('ARCHIVED', $archived->state->value);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$archived->id}/clone")
            ->assertCreated()
            ->assertJsonPath('data.state', 'DRAFT');
    }

    public function test_archive_last_published_returns_warning(): void
    {
        $version = $this->validDraft();
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/publish", ['version' => $version->version])
            ->assertOk();

        $published = $version->fresh();
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$published->id}/archive", [
                'version' => $published->version,
                'reason' => 'sunset',
            ])
            ->assertOk();

        $this->assertSame(
            'LAST_PUBLISHED_ARCHIVED',
            $response->json('meta.warnings.0.code'),
        );
    }

    public function test_stage_permission_rejects_user_id(): void
    {
        $version = $this->validDraft();
        $stage = $version->stages()->where('is_initial', true)->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$stage->id}/permissions", [
                'organization_id' => $this->org->id,
                'user_id' => $this->admin->id,
                'access_level' => StageAccessLevel::EXECUTE->value,
                'display_label' => 'Pinned user',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_effective_executors_endpoint_returns_counts(): void
    {
        $version = $this->validDraft();
        $stage = $version->stages()->where('is_initial', true)->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/workflow-stages/{$stage->id}/effective-executors")
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('data.total_executors'));
        $this->assertNotEmpty($response->json('data.permissions'));
    }

    public function test_graph_display_label_is_deterministic(): void
    {
        $version = $this->validDraft();
        $stage = $version->stages()->where('is_initial', true)->firstOrFail();
        $stage->update(['name' => 'Canonical Stage Name']);

        $graphA = app(WorkflowGraphService::class)->build($version->fresh());
        $graphB = app(WorkflowGraphService::class)->build($version->fresh());

        $this->assertSame($graphA, $graphB);
        $node = collect($graphA['nodes'])->firstWhere('id', $stage->id);
        $this->assertSame('Canonical Stage Name', $node['display_label']);
    }

    public function test_graph_is_return_reads_transition_type(): void
    {
        $version = $this->validDraft();
        $transition = $version->transitions()->firstOrFail();
        $transition->update(['transition_type' => WorkflowTransitionType::RETURN]);

        $edge = collect(app(WorkflowGraphService::class)->build($version->fresh())['edges'])
            ->firstWhere('id', $transition->id);

        $this->assertTrue($edge['is_return']);
    }
}

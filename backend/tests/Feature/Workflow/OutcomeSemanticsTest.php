<?php

namespace Tests\Feature\Workflow;

use App\Enums\FinalOutcome;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\EngineTransitionService;
use App\Services\Workflow\WorkflowVersionValidator;
use App\Support\EngineRequestStatus;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutcomeSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private User $entry;

    private Organization $bankOrg;

    private Role $entryRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->entry = $this->firstUserWithRole(UserRole::DATA_ENTRY);
        $this->bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $this->entryRole = Role::query()->where('code', 'intake')->firstOrFail();
    }

    public function test_publish_blocked_when_final_stage_lacks_outcome(): void
    {
        $version = $this->draftWithFinalStages([
            ['code' => 'done', 'name' => 'Done', 'outcome' => null],
        ]);

        $errors = collect(app(WorkflowVersionValidator::class)->validate($version))->pluck('code');
        $this->assertContains('FINAL_STAGE_NO_OUTCOME', $errors->all());
    }

    public function test_transition_into_each_final_outcome_sets_matching_status(): void
    {
        foreach (
            [
                [FinalOutcome::COMPLETED, EngineRequestStatus::CLOSED],
                [FinalOutcome::REJECTED, EngineRequestStatus::REJECTED],
                [FinalOutcome::CANCELLED, EngineRequestStatus::CANCELLED],
                [FinalOutcome::ABANDONED, EngineRequestStatus::ABANDONED],
            ] as [$outcome, $expectedStatus]
        ) {
            $version = $this->publishedTwoStageWorkflow($outcome);
            $request = $this->createActiveRequestOnStage($version, $version->stages()->where('is_initial', true)->firstOrFail());
            $transition = $version->transitions()->firstOrFail();

            app(EngineTransitionService::class)->execute(
                $request,
                $transition->id,
                null,
                [],
                $request->version,
                $this->entry,
            );

            $this->assertSame($expectedStatus, $request->fresh()->status);
        }
    }

    public function test_final_stages_created_via_api_require_outcome_when_marked_final(): void
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'api-stage', 'name' => 'API Stage']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);
        $admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);

        $this->actingAs($admin)->postJson("/api/v1/workflow-versions/{$version->id}/stages", [
            'code' => 'done',
            'name' => 'Done',
            'is_final' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['final_outcome']);
    }

    public function test_summary_counts_all_statuses_and_list_filter_accepts_new_statuses(): void
    {
        $version = $this->publishedInitialOnlyWorkflow();
        $stageId = $version->stages()->firstOrFail()->id;
        $statuses = [
            EngineRequestStatus::ACTIVE,
            EngineRequestStatus::CLOSED,
            EngineRequestStatus::REJECTED,
            EngineRequestStatus::CANCELLED,
            EngineRequestStatus::ABANDONED,
        ];

        foreach ($statuses as $index => $status) {
            EngineRequest::query()->create([
                'workflow_version_id' => $version->id,
                'current_stage_id' => $stageId,
                'reference' => "ENG-SUM-{$index}",
                'status' => $status,
                'created_by' => $this->entry->id,
                'bank_id' => $this->entry->bank_id,
                'version' => 1,
            ]);
        }

        // COMMITTEE_DIRECTOR sits in the national_committee organization
        // (OrganizationClassification::NATIONAL_COMMITTEE), which is what
        // DataScope::forUser() (used by ReportController::applyScope) checks
        // for system-wide report visibility. CBY_ADMIN's org
        // (system_administration) is classified OTHER and does NOT get
        // systemWide scope from DataScope, even though it holds reports:VIEW
        // via the system_admin screen_permissions grant — see
        // EngineReportTest for the same finding.
        $admin = $this->firstUserWithRole(UserRole::COMMITTEE_DIRECTOR);

        $this->actingAs($admin)->getJson('/api/v1/reports/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.active', 1)
            ->assertJsonPath('data.closed', 1)
            ->assertJsonPath('data.rejected', 1)
            ->assertJsonPath('data.cancelled', 1)
            ->assertJsonPath('data.abandoned', 1);

        $this->actingAs($this->entry)->getJson('/api/v1/engine-requests?status=ABANDONED')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function draftWithFinalStages(array $finals): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'out_'.uniqid(), 'name' => 'Outcome']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ]);
        $intake = $version->stages()->create([
            'code' => 'intake',
            'name' => 'Intake',
            'is_initial' => true,
            'sort_order' => 0,
        ]);
        $intake->stagePermissions()->create([
            'organization_id' => $this->bankOrg->id,
            'role_id' => $this->entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Entry',
        ]);
        $approve = WorkflowAction::query()->create(['code' => 'APPROVE_'.uniqid(), 'name' => 'Approve', 'kind' => 'APPROVE', 'is_active' => true]);
        foreach ($finals as $index => $final) {
            $stage = $version->stages()->create([
                'code' => $final['code'],
                'name' => $final['name'],
                'is_final' => true,
                'final_outcome' => $final['outcome'],
                'sort_order' => $index + 1,
            ]);
            $version->transitions()->create([
                'from_stage_id' => $intake->id,
                'to_stage_id' => $stage->id,
                'action_id' => $approve->id,
            ]);
        }

        return $version->refresh();
    }

    private function publishedTwoStageWorkflow(FinalOutcome $outcome): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'two_'.uniqid(), 'name' => 'Two Stage']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
        ]);
        $intake = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'intake',
            'name' => 'Intake',
            'is_initial' => true,
            'sort_order' => 0,
        ]);
        $final = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'final',
            'name' => 'Final',
            'is_final' => true,
            'final_outcome' => $outcome,
            'sort_order' => 1,
        ]);
        $review = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'review',
            'name' => 'Review',
            'sort_order' => 2,
        ]);
        foreach ([$intake, $final, $review] as $stage) {
            StagePermission::query()->create([
                'stage_id' => $stage->id,
                'organization_id' => $this->bankOrg->id,
                'role_id' => $this->entryRole->id,
                'access_level' => StageAccessLevel::EXECUTE,
                'display_label' => 'Entry',
            ]);
        }
        $action = WorkflowAction::query()->create(['code' => 'ADV_'.uniqid(), 'name' => 'Advance', 'kind' => 'APPROVE', 'is_active' => true]);
        WorkflowTransition::query()->create([
            'workflow_version_id' => $version->id,
            'from_stage_id' => $intake->id,
            'to_stage_id' => $final->id,
            'action_id' => $action->id,
        ]);

        return $version->refresh();
    }

    private function publishedInitialOnlyWorkflow(): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'init_'.uniqid(), 'name' => 'Initial Only']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
        ]);
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'create',
            'name' => 'Create',
            'is_initial' => true,
            'is_final' => true,
            'final_outcome' => FinalOutcome::COMPLETED,
        ]);
        StagePermission::query()->create([
            'stage_id' => $stage->id,
            'organization_id' => $this->bankOrg->id,
            'role_id' => $this->entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Entry',
        ]);

        return $version->refresh();
    }

    private function createActiveRequestOnStage(WorkflowVersion $version, WorkflowStage $stage): EngineRequest
    {
        return EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'ENG-'.uniqid(),
            'status' => EngineRequestStatus::ACTIVE,
            'created_by' => $this->entry->id,
            'bank_id' => $this->entry->bank_id,
            'version' => 1,
        ]);
    }
}

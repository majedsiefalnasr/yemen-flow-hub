<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\Merchant;
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
use Illuminate\Support\Str;
use Tests\TestCase;

class SelectFieldMembershipValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private Bank $otherBank;

    private Merchant $ownMerchant;

    private Merchant $otherMerchant;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private WorkflowStage $reviewStage;

    private WorkflowTransition $submitTransition;

    private WorkflowTransition $reviewTransition;

    private FieldDefinition $coverageField;

    private FieldDefinition $merchantField;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->setUpWorkflow();
    }

    private function setUpWorkflow(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $entryRole = Role::where('code', 'intake')->first();
        $entryTeam = Team::where('code', 'entry')->first();

        $this->bank = Bank::create([
            'name' => 'Test Bank',
            'code' => 'TST',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->otherBank = Bank::create([
            'name' => 'Other Bank',
            'code' => 'OTH',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@test.bank',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->ownMerchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Own Merchant',
            'tax_number' => 'OWN-1',
            'status' => 'ACTIVE',
        ]);

        $this->otherMerchant = Merchant::create([
            'bank_id' => $this->otherBank->id,
            'name' => 'Other Merchant',
            'tax_number' => 'OTH-1',
            'status' => 'ACTIVE',
        ]);

        $definition = WorkflowDefinition::create([
            'code' => 'SELECT_VALIDATION_WF',
            'name' => 'Select Validation Workflow',
            'is_active' => true,
        ]);

        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->initialStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'DATA_ENTRY',
            'name' => 'Data Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        $this->reviewStage = WorkflowStage::create([
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
            'display_label' => 'Data Entry',
            'version' => 1,
        ]);

        // The executor also needs EXECUTE on REVIEW: store() now consumes the
        // initial submit transition atomically, so the select-membership
        // validation checks below run on a SECOND, LATER transition
        // (REVIEW -> FINAL), not the one store() already executed.
        $finalStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'FINAL',
            'name' => 'Final',
            'sort_order' => 3,
            'is_initial' => false,
            'is_final' => true,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->reviewStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Review',
            'version' => 1,
        ]);

        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'main',
            'label' => 'Main Fields',
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->coverageField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'coverage',
            'label' => 'Coverage',
            'type' => FieldType::SELECT,
            'options' => [
                ['value' => 'full', 'label' => 'Full'],
                ['value' => 'partial', 'label' => 'Partial'],
            ],
            'is_required' => false,
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->merchantField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'merchant_pick',
            'label' => 'Merchant',
            'type' => FieldType::DYNAMIC_SELECT,
            'dynamic_source' => 'MERCHANTS',
            'is_required' => false,
            'sort_order' => 2,
            'version' => 1,
        ]);

        $submitAction = WorkflowAction::create([
            'code' => 'SUBMIT',
            'name' => 'Submit',
            'kind' => 'DRAFT',
            'is_active' => true,
            'version' => 1,
        ]);

        $reviewAction = WorkflowAction::create([
            'code' => 'REVIEW_APPROVE',
            'name' => 'Review Approve',
            'kind' => 'APPROVE',
            'is_active' => true,
            'version' => 1,
        ]);

        $this->submitTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->initialStage->id,
            'to_stage_id' => $this->reviewStage->id,
            'action_id' => $submitAction->id,
            'requires_comment' => false,
            'version' => 1,
        ]);

        $this->reviewTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->reviewStage->id,
            'to_stage_id' => $finalStage->id,
            'action_id' => $reviewAction->id,
            'requires_comment' => false,
            'version' => 1,
        ]);
    }

    private function createRequest(array $data = []): EngineRequest
    {
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', array_merge([
                'workflow_version_id' => $this->version->id,
                'merchant_id' => $this->ownMerchant->id,
                'data' => ['coverage' => 'full'],
            ], $data));

        $response->assertCreated();

        return EngineRequest::findOrFail($response->json('data.id'));
    }

    public function test_create_rejects_invalid_static_select_value(): void
    {
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
                'workflow_version_id' => $this->version->id,
                'merchant_id' => $this->ownMerchant->id,
                'data' => ['coverage' => 'not-a-valid-option'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.coverage', 'The selected value is not a valid option.');
    }

    public function test_transition_rejects_invalid_static_select_value(): void
    {
        // createRequest() already executes the initial submit transition, landing
        // the request on reviewStage — this is the SECOND, LATER transition
        // (reviewStage -> finalStage) under test here.
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->reviewTransition->id,
            'data' => ['coverage' => 'not-a-valid-option'],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.coverage', 'The selected value is not a valid option.');
    }

    public function test_transition_rejects_out_of_scope_dynamic_select_value(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->reviewTransition->id,
            'data' => ['merchant_pick' => $this->otherMerchant->id],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.merchant_pick', 'The selected value is not a valid option.');
    }

    public function test_transition_accepts_valid_select_values(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->reviewTransition->id,
            'data' => [
                'coverage' => 'partial',
                'merchant_pick' => $this->ownMerchant->id,
            ],
            'version' => $request->version,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.data.coverage', 'partial')
            ->assertJsonPath('data.data.merchant_pick', $this->ownMerchant->id);
    }
}

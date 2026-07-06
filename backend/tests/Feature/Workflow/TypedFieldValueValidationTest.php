<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
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
use Tests\TestCase;

class TypedFieldValueValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private Merchant $merchant;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private WorkflowStage $reviewStage;

    private WorkflowTransition $submitTransition;

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

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@test.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Test Merchant',
            'tax_number' => '123456789',
            'status' => 'ACTIVE',
        ]);

        $definition = WorkflowDefinition::create([
            'code' => 'TYPED_VALUE_WF',
            'name' => 'Typed Value Workflow',
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

        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'typed',
            'label' => 'Typed Fields',
            'sort_order' => 1,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'invoice_date',
            'label' => 'Invoice Date',
            'type' => FieldType::DATE,
            'is_required' => false,
            'sort_order' => 1,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'agree_terms',
            'label' => 'Agree Terms',
            'type' => FieldType::CHECKBOX,
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

        $this->submitTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->initialStage->id,
            'to_stage_id' => $this->reviewStage->id,
            'action_id' => $submitAction->id,
            'requires_comment' => false,
            'version' => 1,
        ]);
    }

    private function createRequest(array $data = []): EngineRequest
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', array_merge([
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => $data,
        ], []));

        $response->assertCreated();

        return EngineRequest::findOrFail($response->json('data.id'));
    }

    public function test_create_rejects_invalid_date_value(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['invoice_date' => '06/25/2026'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.invoice_date', 'The date must be a valid ISO-8601 date (YYYY-MM-DD).');
    }

    public function test_draft_save_rejects_invalid_date_value(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['invoice_date' => '2026-13-40'],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.invoice_date', 'The date must be a valid ISO-8601 date (YYYY-MM-DD).');
    }

    public function test_transition_rejects_invalid_date_value(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => ['invoice_date' => 'not-a-date'],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.invoice_date', 'The date must be a valid ISO-8601 date (YYYY-MM-DD).');
    }

    public function test_draft_save_rejects_non_boolean_checkbox_value(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['agree_terms' => 'yes'],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.agree_terms', 'The checkbox value must be true or false.');
    }

    public function test_draft_save_accepts_valid_typed_values(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => [
                'invoice_date' => '2026-06-25',
                'agree_terms' => true,
            ],
            'version' => $request->version,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.data.invoice_date', '2026-06-25')
            ->assertJsonPath('data.data.agree_terms', true);
    }
}

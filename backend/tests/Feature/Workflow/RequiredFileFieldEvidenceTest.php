<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RequiredFileFieldEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private Merchant $merchant;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private WorkflowStage $reviewStage;

    private WorkflowTransition $submitTransition;

    private FieldDefinition $docField;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
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
            'code' => 'FILE_EVIDENCE_WF',
            'name' => 'File Evidence Workflow',
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
            'name' => 'docs',
            'label' => 'Documents',
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->docField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'supporting_doc',
            'label' => 'Supporting Document',
            'type' => FieldType::FILE,
            'allowed_file_types' => ['pdf'],
            'max_file_size' => 100,
            'is_required' => true,
            'sort_order' => 1,
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
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => $data,
        ]);

        $response->assertCreated();

        return EngineRequest::findOrFail($response->json('data.id'));
    }

    private function uploadDocument(EngineRequest $request, ?int $fieldId = null): int
    {
        $file = UploadedFile::fake()->create('evidence.pdf', 50, 'application/pdf');
        $payload = ['file' => $file];
        if ($fieldId !== null) {
            $payload['field_id'] = $fieldId;
        }

        $response = $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            $payload,
        );
        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    private function submit(EngineRequest $request, array $data = []): TestResponse
    {
        return $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => $data,
            'version' => $request->version,
        ]);
    }

    public function test_draft_save_allows_missing_required_file_evidence(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['supporting_doc' => []],
            'version' => $request->version,
        ]);

        $response->assertOk();
    }

    public function test_transition_rejects_required_file_field_without_linked_document(): void
    {
        $request = $this->createRequest();

        $response = $this->submit($request);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'This field is required.');
    }

    public function test_transition_rejects_when_document_is_not_linked_to_field(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request);

        $response = $this->submit($request, ['supporting_doc' => [$docId]]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'An uploaded document is required for this field.');
    }

    public function test_transition_rejects_when_linked_document_is_soft_deleted(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request, $this->docField->id);

        EngineRequestDocument::query()->whereKey($docId)->delete();

        $response = $this->submit($request, ['supporting_doc' => [$docId]]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'The referenced document was not found for this request.');
    }

    public function test_transition_rejects_when_linked_document_violates_field_constraints(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request, $this->docField->id);

        EngineRequestDocument::query()->whereKey($docId)->update([
            'mime' => 'image/png',
        ]);

        $response = $this->submit($request, ['supporting_doc' => [$docId]]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'Only the following file types are allowed: pdf.');
    }

    public function test_transition_succeeds_with_linked_document_and_valid_reference(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request, $this->docField->id);

        $response = $this->submit($request, ['supporting_doc' => [$docId]]);

        $response->assertOk()
            ->assertJsonPath('data.current_stage.id', $this->reviewStage->id)
            ->assertJsonPath('data.data.supporting_doc', [$docId]);
    }
}

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
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileFieldDocumentReferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private Merchant $merchant;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

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
            'code' => 'FILE_REF_WF',
            'name' => 'File Reference Workflow',
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
            'is_required' => false,
            'sort_order' => 1,
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

    public function test_draft_save_rejects_client_metadata_for_file_field(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => [
                'supporting_doc' => ['mime' => 'application/pdf', 'size_kb' => 50],
            ],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'File fields must reference uploaded documents.');
    }

    public function test_draft_save_rejects_document_from_another_request(): void
    {
        $requestA = $this->createRequest();
        $requestB = $this->createRequest();
        $foreignDocId = $this->uploadDocument($requestA, $this->docField->id);

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$requestB->id}/draft", [
            'data' => ['supporting_doc' => [$foreignDocId]],
            'version' => $requestB->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'The referenced document was not found for this request.');
    }

    public function test_draft_save_rejects_document_linked_to_different_field(): void
    {
        $request = $this->createRequest();
        $otherField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $this->docField->field_group_id,
            'key' => 'other_doc',
            'label' => 'Other Document',
            'type' => FieldType::FILE,
            'is_required' => false,
            'sort_order' => 2,
            'version' => 1,
        ]);

        $docId = $this->uploadDocument($request, $otherField->id);

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['supporting_doc' => [$docId]],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'The referenced document is not linked to this field.');
    }

    public function test_draft_save_rejects_document_exceeding_max_file_size(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request, $this->docField->id);

        EngineRequestDocument::query()->whereKey($docId)->update([
            'size' => 200 * 1024,
        ]);

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['supporting_doc' => [$docId]],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'The file must not exceed 100 KB.');
    }

    public function test_draft_save_rejects_document_with_disallowed_mime(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request, $this->docField->id);

        EngineRequestDocument::query()->whereKey($docId)->update([
            'mime' => 'image/png',
        ]);

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['supporting_doc' => [$docId]],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'Only the following file types are allowed: pdf.');
    }

    public function test_draft_save_accepts_valid_document_reference(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request, $this->docField->id);

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['supporting_doc' => [$docId]],
            'version' => $request->version,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.data.supporting_doc', [$docId]);
    }

    public function test_create_rejects_file_field_document_reference_without_request(): void
    {
        $request = $this->createRequest();
        $docId = $this->uploadDocument($request, $this->docField->id);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['supporting_doc' => [$docId]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.supporting_doc', 'File fields must reference uploaded documents.');
    }
}

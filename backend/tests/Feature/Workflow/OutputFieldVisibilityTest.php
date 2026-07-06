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
use App\Models\StageFieldRule;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OutputFieldVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private User $viewer;

    private Bank $bank;

    private Merchant $merchant;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private FieldDefinition $publicField;

    private FieldDefinition $hiddenField;

    private FieldDefinition $fileField;

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
        $cbyOrg = Organization::where('code', 'national_committee')->first();
        $entryRole = Role::where('code', 'intake')->first();
        $supportRole = Role::where('code', 'support')->first();
        $entryTeam = Team::where('code', 'entry')->first();
        $supportTeam = Team::where('code', 'support')->first();

        $this->bank = Bank::create([
            'name' => 'Visibility Bank',
            'code' => 'VIS',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@visibility.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@visibility.cby',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPPORT_COMMITTEE,
            'bank_id' => null,
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $this->viewer->teams()->attach($supportTeam);
        $this->viewer->roles()->attach($supportRole);

        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Visibility Merchant',
            'tax_number' => '987654321',
            'status' => 'ACTIVE',
        ]);

        $definition = WorkflowDefinition::create([
            'code' => 'VIS_WF',
            'name' => 'Visibility Workflow',
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

        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $cbyOrg->id,
            'role_id' => $supportRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Data Entry (View)',
            'version' => 1,
        ]);

        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'main',
            'label' => 'Main',
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->publicField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => 'NUMBER',
            'is_required' => false,
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->hiddenField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'internal_note',
            'label' => 'Internal Note',
            'type' => 'TEXT',
            'is_required' => false,
            'sort_order' => 2,
            'version' => 1,
        ]);

        $this->fileField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'confidential_doc',
            'label' => 'Confidential Doc',
            'type' => FieldType::FILE->value,
            'allowed_file_types' => ['pdf'],
            'is_required' => false,
            'sort_order' => 3,
            'version' => 1,
        ]);

        StageFieldRule::create([
            'stage_id' => $this->initialStage->id,
            'field_id' => $this->publicField->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_required' => false,
            'version' => 1,
        ]);

        StageFieldRule::create([
            'stage_id' => $this->initialStage->id,
            'field_id' => $this->hiddenField->id,
            'is_visible' => false,
            'is_editable' => false,
            'is_required' => false,
            'version' => 1,
        ]);

        StageFieldRule::create([
            'stage_id' => $this->initialStage->id,
            'field_id' => $this->fileField->id,
            'is_visible' => false,
            'is_editable' => false,
            'is_required' => false,
            'version' => 1,
        ]);
    }

    private function makeRequest(array $dataOverrides = []): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->initialStage->id,
            'created_by' => $this->executor->id,
            'merchant_id' => $this->merchant->id,
            'bank_id' => $this->bank->id,
            'reference' => 'ENG-VIS-000001',
            'status' => 'ACTIVE',
            'data' => array_merge([
                'amount' => 10000,
                'internal_note' => 'secret value',
            ], $dataOverrides),
            'version' => 1,
        ]);
    }

    public function test_viewer_without_field_visibility_sees_filtered_data_on_show(): void
    {
        $engineRequest = $this->makeRequest();

        $response = $this->actingAs($this->viewer)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}");

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertArrayHasKey('amount', $data);
        $this->assertArrayNotHasKey('internal_note', $data);
        $this->assertSame(10000, $data['amount']);
    }

    public function test_viewer_without_field_visibility_sees_filtered_data_on_list(): void
    {
        $engineRequest = $this->makeRequest();

        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $engineRequest->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('amount', $row['data']);
        $this->assertArrayNotHasKey('internal_note', $row['data']);
    }

    public function test_form_schema_omits_hidden_fields_for_viewer(): void
    {
        $engineRequest = $this->makeRequest();

        $response = $this->actingAs($this->viewer)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}/form-schema");

        $response->assertOk();
        $fieldKeys = collect($response->json('data.field_groups'))
            ->flatMap(fn (array $group) => collect($group['fields'])->pluck('key'))
            ->all();

        $this->assertContains('amount', $fieldKeys);
        $this->assertNotContains('internal_note', $fieldKeys);
        $this->assertNotContains('confidential_doc', $fieldKeys);
    }

    public function test_executor_sees_only_visible_fields_in_output(): void
    {
        $engineRequest = $this->makeRequest();

        $response = $this->actingAs($this->executor)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}");

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertArrayHasKey('amount', $data);
        $this->assertArrayNotHasKey('internal_note', $data);
    }

    public function test_hidden_field_linked_document_not_listed_for_viewer(): void
    {
        $engineRequest = $this->makeRequest();
        $hiddenDoc = $this->seedDocument($engineRequest, $this->fileField->id, 'hidden.pdf');
        $publicDoc = $this->seedDocument($engineRequest, null, 'general.pdf');

        $response = $this->actingAs($this->viewer)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}/documents");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($hiddenDoc->id, $ids);
        $this->assertContains($publicDoc->id, $ids);
    }

    public function test_hidden_field_linked_document_not_downloadable_for_viewer(): void
    {
        $engineRequest = $this->makeRequest();
        $hiddenDoc = $this->seedDocument($engineRequest, $this->fileField->id, 'hidden.pdf');

        $this->actingAs($this->viewer)
            ->get("/api/v1/engine-requests/{$engineRequest->id}/documents/{$hiddenDoc->id}/download")
            ->assertNotFound();
    }

    public function test_unlinked_document_remains_downloadable_for_viewer(): void
    {
        $engineRequest = $this->makeRequest();
        $publicDoc = $this->seedDocument($engineRequest, null, 'general.pdf');

        $this->actingAs($this->viewer)
            ->get("/api/v1/engine-requests/{$engineRequest->id}/documents/{$publicDoc->id}/download")
            ->assertOk();
    }

    private function seedDocument(EngineRequest $engineRequest, ?int $fieldId, string $name): EngineRequestDocument
    {
        $path = "engine-requests/{$engineRequest->id}/{$name}";
        Storage::disk('private')->put($path, '%PDF-1.4 test');

        return EngineRequestDocument::create([
            'request_id' => $engineRequest->id,
            'field_id' => $fieldId,
            'uploaded_by' => $this->executor->id,
            'stage_id' => $this->initialStage->id,
            'original_name' => $name,
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 128,
            'checksum' => hash('sha256', '%PDF-1.4 test'),
        ]);
    }
}

<?php

namespace Tests\Feature\Engine;

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
use App\Models\StageFieldRule;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\StageHookRegistry;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EngineRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private User $viewer;

    private User $outsideUser;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private WorkflowStage $reviewStage;

    private WorkflowStage $finalStage;

    private WorkflowTransition $submitTransition;

    private WorkflowTransition $approveTransition;

    private WorkflowAction $submitAction;

    private WorkflowAction $approveAction;

    private Bank $bank;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->setUpWorkflow();
    }

    private function setUpWorkflow(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $cbyOrg = Organization::where('code', 'national_committee')->first();

        $this->bank = Bank::create([
            'name' => 'Test Bank',
            'code' => 'TST',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $entryRole = Role::where('code', 'intake')->first();
        $supportRole = Role::where('code', 'support')->first();
        $entryTeam = Team::where('code', 'entry')->first();
        $supportTeam = Team::where('code', 'support')->first();

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

        $this->viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@cby.gov',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPPORT_COMMITTEE,
            'bank_id' => null,
            'organization_id' => $cbyOrg->id,
            'is_active' => true,
        ]);
        $this->viewer->teams()->attach($supportTeam);
        $this->viewer->roles()->attach($supportRole);

        $otherBank = Bank::create([
            'name' => 'Other Bank',
            'code' => 'OTH',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);
        $this->outsideUser = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@other.bank',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $otherBank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->outsideUser->teams()->attach($entryTeam);
        $this->outsideUser->roles()->attach($entryRole);

        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Test Merchant',
            'tax_number' => '123456789',
            'status' => 'ACTIVE',
        ]);

        $def = WorkflowDefinition::create([
            'code' => 'IMPORT_FINANCING',
            'name' => 'Import Financing',
            'is_active' => true,
        ]);

        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
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
            'sla_duration_minutes' => 60,
            'version' => 1,
        ]);

        $this->reviewStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'REVIEW',
            'name' => 'Review',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => false,
            'sla_duration_minutes' => 120,
            'version' => 1,
        ]);

        $this->finalStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'COMPLETED',
            'name' => 'Completed',
            'sort_order' => 3,
            'is_initial' => false,
            'is_final' => true,
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
            'stage_id' => $this->reviewStage->id,
            'organization_id' => $cbyOrg->id,
            'role_id' => $supportRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Support Review',
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

        StagePermission::create([
            'stage_id' => $this->reviewStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Review (View)',
            'version' => 1,
        ]);

        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'main',
            'label' => 'Main Fields',
            'sort_order' => 1,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => 'NUMBER',
            'is_required' => false,
            'sort_order' => 1,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'currency',
            'label' => 'Currency',
            'type' => 'TEXT',
            'is_required' => false,
            'sort_order' => 2,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'invoice_number',
            'label' => 'Invoice Number',
            'type' => 'TEXT',
            'is_required' => false,
            'sort_order' => 3,
            'version' => 1,
        ]);

        $this->submitAction = WorkflowAction::create([
            'code' => 'SUBMIT',
            'name' => 'Submit',
            'kind' => 'DRAFT',
            'is_active' => true,
            'version' => 1,
        ]);

        $this->approveAction = WorkflowAction::create([
            'code' => 'APPROVE',
            'name' => 'Approve',
            'kind' => 'APPROVE',
            'is_active' => true,
            'version' => 1,
        ]);

        $this->submitTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->initialStage->id,
            'to_stage_id' => $this->reviewStage->id,
            'action_id' => $this->submitAction->id,
            'requires_comment' => false,
            'version' => 1,
        ]);

        $this->approveTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->reviewStage->id,
            'to_stage_id' => $this->finalStage->id,
            'action_id' => $this->approveAction->id,
            'requires_comment' => true,
            'version' => 1,
        ]);
    }

    private function createRequest(array $data = []): EngineRequest
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', array_merge([
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 50000, 'currency' => 'USD', 'invoice_number' => 'INV-001'],
        ], $data));

        $response->assertCreated();

        return EngineRequest::findOrFail($response->json('data.id'));
    }

    // ── 18.5.1: Create ──────────────────────────────────────────────────

    public function test_create_with_execute_permission(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 50000, 'currency' => 'USD', 'invoice_number' => 'INV-001'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.amount', '50000.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.invoice_number', 'INV-001');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('engine_requests', ['id' => $id, 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('workflow_history', ['request_id' => $id, 'action_code' => 'CREATE']);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $id, 'action' => 'REQUEST_CREATED']);
    }

    public function test_create_accepts_empty_data_for_a_blank_draft(): void
    {
        // The "new request" flow spins up an empty draft, then fills it in the
        // wizard, so an empty data object must be accepted.
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'data' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_create_rejects_missing_data_key(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    }

    public function test_create_blocked_without_execute(): void
    {
        // Support committee holds VIEW (not EXECUTE) on the initial stage, but WP-1
        // blocks creation earlier for non-banking-sector organizations.
        $response = $this->actingAs($this->viewer)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'data' => ['amount' => 100],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'CREATION_NOT_ALLOWED_FOR_ORGANIZATION');
    }

    public function test_create_reference_unique(): void
    {
        $this->createRequest();
        $this->createRequest(['data' => ['amount' => 200, 'currency' => 'EUR', 'invoice_number' => 'INV-002']]);

        $refs = EngineRequest::pluck('reference')->all();
        $this->assertCount(2, array_unique($refs));
    }

    public function test_create_merchant_out_of_scope(): void
    {
        $otherMerchant = Merchant::create([
            'bank_id' => Bank::where('code', 'OTH')->first()->id,
            'name' => 'Other Merchant',
            'tax_number' => '999999999',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $otherMerchant->id,
            'data' => ['amount' => 100],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'MERCHANT_OUT_OF_SCOPE');
    }

    public function test_projection_columns_populated(): void
    {
        $request = $this->createRequest();

        $this->assertEquals('50000.00', $request->amount);
        $this->assertEquals('USD', $request->currency);
        $this->assertEquals('INV-001', $request->invoice_number);
    }

    // ── 18.5.2: List ────────────────────────────────────────────────────

    public function test_list_scoped_to_accessible_stages(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests');
        $response->assertOk()->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($this->viewer)->getJson('/api/v1/engine-requests');
        $response->assertOk()->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($this->outsideUser)->getJson('/api/v1/engine-requests');
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_list_filters(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests?status=ACTIVE');
        $response->assertOk()->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests?status=CLOSED');
        $response->assertOk()->assertJsonPath('meta.total', 0);

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests?search=INV-001');
        $response->assertOk()->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests?search=NOPE');
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ── 18.5.3: My Queue (دوري) ──────────────────────────────────────────

    public function test_queue_returns_execute_only(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests/my-queue');
        $response->assertOk()->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($this->viewer)->getJson('/api/v1/engine-requests/my-queue');
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_queue_excludes_closed(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'version' => $request->version,
            'data' => [],
        ])->assertOk();

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests/my-queue');
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ── 18.5.4: Execute Transition ───────────────────────────────────────

    public function test_transition_happy_path(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'comment' => 'Submitting for review',
            'data' => [],
            'version' => $request->version,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_stage.code', 'REVIEW');

        $this->assertDatabaseHas('workflow_history', [
            'request_id' => $request->id,
            'from_stage_id' => $this->initialStage->id,
            'to_stage_id' => $this->reviewStage->id,
            'action_code' => 'SUBMIT',
        ]);
    }

    public function test_transition_stale_version_409(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => 999,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'REQUEST_STALE');
    }

    public function test_transition_not_available(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'data' => [],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'TRANSITION_NOT_AVAILABLE');
    }

    public function test_transition_non_executor_forbidden(): void
    {
        $request = $this->createRequest();

        // The viewer holds only VIEW on the initial stage, so the policy's execute
        // gate (bank scope + EXECUTE) rejects before the service runs — the API
        // returns the policy-level forbidden envelope.
        $response = $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_transition_comment_required(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $request->refresh();

        $response = $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'data' => [],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'COMMENT_REQUIRED');
    }

    public function test_transition_to_final_closes_request(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $request->refresh();

        $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'comment' => 'Approved',
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $request->refresh();
        $this->assertEquals('CLOSED', $request->status);
        $this->assertEquals($this->finalStage->id, $request->current_stage_id);
    }

    public function test_closed_request_rejects_transition(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $request->refresh();

        $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'comment' => 'Approved',
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $request->refresh();

        $response = $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'comment' => 'Again',
            'data' => [],
            'version' => $request->version,
        ]);

        // A closed request is non-actionable: the policy's execute gate (which checks
        // isActive) forbids before the service-level REQUEST_CLOSED path is reached.
        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    // ── 18.5.5: Save Draft ───────────────────────────────────────────────

    public function test_draft_save_persists_data(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['amount' => 75000],
            'version' => $request->version,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.data.amount', 75000);

        $request->refresh();
        $this->assertEquals($this->initialStage->id, $request->current_stage_id);
        $this->assertEquals('ACTIVE', $request->status);
    }

    public function test_draft_save_stale_version(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['amount' => 75000],
            'version' => 999,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'REQUEST_STALE');
    }

    public function test_draft_non_executor_blocked(): void
    {
        $request = $this->createRequest();

        // The viewer lacks EXECUTE on the initial stage, so the policy execute gate
        // forbids the draft before the service runs.
        $response = $this->actingAs($this->viewer)->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
            'data' => ['amount' => 75000],
            'version' => $request->version,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    // ── 18.5.6: Documents ────────────────────────────────────────────────

    public function test_upload_pdf_document(): void
    {
        Storage::fake('private');
        $request = $this->createRequest();

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file],
        );

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('engine_request_documents', [
            'request_id' => $request->id,
            'original_name' => 'doc.pdf',
        ]);
    }

    public function test_upload_non_pdf_rejected(): void
    {
        Storage::fake('private');
        $request = $this->createRequest();

        $file = UploadedFile::fake()->create('doc.txt', 100, 'text/plain');

        $response = $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file],
        );

        $response->assertStatus(422);
    }

    public function test_list_documents(): void
    {
        Storage::fake('private');
        $request = $this->createRequest();

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file],
        )->assertCreated();

        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/documents");
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_delete_document_before_leaving_stage(): void
    {
        Storage::fake('private');
        $request = $this->createRequest();

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $uploadResponse = $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file],
        )->assertCreated();

        $docId = $uploadResponse->json('data.id');

        $response = $this->actingAs($this->executor)->deleteJson(
            "/api/v1/engine-requests/{$request->id}/documents/{$docId}",
        );
        $response->assertOk();

        $this->assertSoftDeleted('engine_request_documents', ['id' => $docId]);
    }

    // ── 18.5.7: History & Graph ──────────────────────────────────────────

    public function test_history_returns_ordered_movements(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $history = $response->json('data');
        $this->assertEquals('CREATE', $history[0]['action_code']);
        $this->assertEquals('SUBMIT', $history[1]['action_code']);
    }

    public function test_graph_marks_executed_current_possible(): void
    {
        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk();

        $nodes = collect($response->json('data.nodes'));
        $dataEntry = $nodes->firstWhere('code', 'DATA_ENTRY');
        $review = $nodes->firstWhere('code', 'REVIEW');
        $completed = $nodes->firstWhere('code', 'COMPLETED');

        $this->assertEquals('executed', $dataEntry['state']);
        $this->assertEquals('current', $review['state']);
        $this->assertEquals('possible', $completed['state']);
    }

    public function test_graph_reports_execute_stage_ids_for_current_user(): void
    {
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk();

        $executeStageIds = $response->json('data.execute_stage_ids');

        // The executor (entry role) holds EXECUTE only on the initial stage; it has
        // just VIEW on the review stage and nothing on the final stage.
        $this->assertContains($this->initialStage->id, $executeStageIds);
        $this->assertNotContains($this->reviewStage->id, $executeStageIds);
        $this->assertNotContains($this->finalStage->id, $executeStageIds);
    }

    // ── 18.5.8: Duplicate Invoice ────────────────────────────────────────

    public function test_duplicate_invoice_warning_on_create(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 200, 'currency' => 'USD', 'invoice_number' => 'INV-001'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('warnings.0.code', 'DUPLICATE_INVOICE');
    }

    public function test_unique_invoice_no_warning(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 200, 'currency' => 'USD', 'invoice_number' => 'UNIQUE-001'],
        ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('warnings', $response->json());
    }

    // ── 18.5.9: Stage Hooks ──────────────────────────────────────────────

    public function test_stage_hook_fires_on_transition(): void
    {
        $hookFired = false;
        $registry = new StageHookRegistry;
        $registry->onStageEntry('REVIEW', function () use (&$hookFired) {
            $hookFired = true;
        });
        $this->app->instance(StageHookRegistry::class, $registry);

        $request = $this->createRequest();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        $this->assertTrue($hookFired);
    }

    public function test_failing_hook_rolls_back_transition(): void
    {
        $registry = new StageHookRegistry;
        $registry->onStageEntry('REVIEW', function () {
            throw new \RuntimeException('Hook failure');
        });
        $this->app->instance(StageHookRegistry::class, $registry);

        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_HOOK_FAILED');

        $request->refresh();
        $this->assertEquals($this->initialStage->id, $request->current_stage_id);
        $this->assertEquals('ACTIVE', $request->status);
    }

    public function test_available_workflows_lists_published_versions_the_user_can_start(): void
    {
        $response = $this->actingAs($this->executor)
            ->getJson('/api/v1/engine-requests/available-workflows');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('version_id')->all();
        $this->assertContains($this->version->id, $ids);
    }

    public function test_available_workflows_excludes_draft_versions(): void
    {
        $draftVersion = WorkflowVersion::create([
            'workflow_definition_id' => $this->version->workflow_definition_id,
            'state' => WorkflowVersionState::DRAFT,
            'version_number' => $this->version->version_number + 1,
            'version' => 1,
        ]);

        $response = $this->actingAs($this->executor)
            ->getJson('/api/v1/engine-requests/available-workflows');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('version_id')->all();
        $this->assertNotContains($draftVersion->id, $ids);
    }

    public function test_available_workflows_excludes_users_without_create_capability(): void
    {
        // `requests` capability is now workflow-derived, not gated by a static 403.
        // A user with no stage assignment on any published workflow simply sees an
        // empty available-workflows list, not a 403 — the per-version stage filter
        // is the sole (and correct) gate.
        $noAccessUser = User::create([
            'name' => 'No Access User',
            'email' => 'noaccess@test.com',
            'password' => bcrypt('password'),
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $this->bank->id,
            'organization_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($noAccessUser)
            ->getJson('/api/v1/engine-requests/available-workflows');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    // ── 18.5.10: Form Schema ─────────────────────────────────────────────

    public function test_form_schema_returns_merged_stage_effective_rules(): void
    {
        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'invoice_details',
            'label' => 'Invoice Details',
            'sort_order' => 2,
            'version' => 1,
        ]);

        $field = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'invoice_amount',
            'label' => 'Invoice Amount',
            'type' => 'NUMBER',
            'is_required' => false,
            'sort_order' => 1,
            'version' => 1,
        ]);

        StageFieldRule::create([
            'stage_id' => $this->initialStage->id,
            'field_id' => $field->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_required' => true,
            'version' => 1,
        ]);

        $engineRequest = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->initialStage->id,
            'created_by' => $this->executor->id,
            'merchant_id' => $this->merchant->id,
            'bank_id' => $this->bank->id,
            'reference' => 'ENG-2026-000001',
            'status' => 'ACTIVE',
            'data' => ['amount' => 50000],
            'version' => 1,
        ]);

        $response = $this->actingAs($this->executor)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}/form-schema");

        $response->assertOk();
        $groups = $response->json('data.field_groups');
        $this->assertCount(2, $groups); // main + invoice_details
        $invoiceDetailsGroup = collect($groups)->firstWhere('name', 'invoice_details');
        $this->assertNotNull($invoiceDetailsGroup);
        $this->assertEquals('invoice_details', $invoiceDetailsGroup['name']);
        $this->assertCount(1, $invoiceDetailsGroup['fields']);
        $returnedField = $invoiceDetailsGroup['fields'][0];
        $this->assertSame('invoice_amount', $returnedField['key']);
        $this->assertTrue($returnedField['is_required']); // stage rule overrides field default false
        $this->assertTrue($returnedField['is_visible']);
        $this->assertTrue($returnedField['is_editable']);
    }

    public function test_form_schema_forbidden_for_user_outside_scope(): void
    {
        $engineRequest = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->initialStage->id,
            'created_by' => $this->executor->id,
            'merchant_id' => $this->merchant->id,
            'bank_id' => $this->bank->id,
            'reference' => 'ENG-2026-000002',
            'status' => 'ACTIVE',
            'data' => ['amount' => 50000],
            'version' => 1,
        ]);

        $response = $this->actingAs($this->outsideUser)
            ->getJson("/api/v1/engine-requests/{$engineRequest->id}/form-schema");

        $response->assertForbidden();
    }
}

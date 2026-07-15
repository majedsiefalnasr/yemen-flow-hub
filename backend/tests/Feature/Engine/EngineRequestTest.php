<?php

namespace Tests\Feature\Engine;

use App\Enums\FieldSemanticTag;
use App\Enums\StageAccessLevel;
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
use App\Support\RoleCodes;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', array_merge([
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
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
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

    public function test_create_accepts_empty_data_and_advances_past_initial_stage(): void
    {
        // store() now creates the request AND atomically executes its initial
        // submit transition in one call, so an empty data object is only accepted
        // when no *required* field exists on the initial stage — true here, since
        // amount/currency/invoice_number are all is_required: false (setUpWorkflow
        // above). The created request lands on reviewStage, not initialStage: there
        // is no more "blank draft that stays put" state in this architecture.
        //
        // Note: the store() response body does not eager-load `currentStage` (only
        // show()/executeAction() do), so `data.current_stage` is absent here even
        // though the DB row has already advanced — asserted via a fresh fetch,
        // matching the pattern used by EngineRequestDeferredSubmissionTest.
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
                'workflow_version_id' => $this->version->id,
                'data' => [],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ACTIVE');

        $request = EngineRequest::findOrFail($response->json('data.id'));
        $this->assertEquals($this->reviewStage->id, $request->current_stage_id);
    }

    public function test_create_rejects_missing_data_key(): void
    {
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
                'workflow_version_id' => $this->version->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    }

    public function test_create_blocked_without_execute(): void
    {
        // Support committee holds VIEW (not EXECUTE) on the initial stage, but WP-1
        // blocks creation earlier for non-banking-sector organizations.
        $response = $this->actingAs($this->viewer)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
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

        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
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

    /**
     * Regression guard for the N+1 identified in WP-8 final review: listing
     * requests must not issue additional queries per row for per-stage field
     * visibility (StageFieldOutputFilter::visibleFieldKeysForStage),
     * FX-stage resolution (FxConfirmationAuthorizationService::resolveFxStage),
     * or can_execute stage-permission checks — those are now eager-loaded or
     * memoized per (workflow_version_id, stage_id) / (user_id, stage_id).
     *
     * One query per additional row remains by design: DataScope::forUser's
     * per-request `EngineRequest::exists()` scope check
     * (FxConfirmationAuthorizationService::requestInScope) is a genuine
     * per-row authorization check keyed by request id and cannot be
     * memoized across distinct rows. The assertion below allows for that
     * single linear query but fails if the N+1 (dozens of queries/row)
     * reappears.
     */
    public function test_list_query_count_does_not_scale_with_row_count(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->createRequest(['data' => ['amount' => 50000, 'currency' => 'USD', 'invoice_number' => 'INV-'.$i]]);
        }

        DB::enableQueryLog();
        $this->actingAs($this->executor)->getJson('/api/v1/engine-requests')->assertOk()->assertJsonPath('meta.total', 2);
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        for ($i = 2; $i < 10; $i++) {
            $this->createRequest(['data' => ['amount' => 50000, 'currency' => 'USD', 'invoice_number' => 'INV-'.$i]]);
        }

        DB::enableQueryLog();
        $this->actingAs($this->executor)->getJson('/api/v1/engine-requests')->assertOk()->assertJsonPath('meta.total', 10);
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $additionalRows = 10 - 2;
        $queryGrowth = $largeCount - $smallCount;

        $this->assertLessThanOrEqual(
            $additionalRows,
            $queryGrowth,
            "Query count grew by {$queryGrowth} across {$additionalRows} additional rows ({$smallCount} for 2 rows vs {$largeCount} for 10 rows), indicating an N+1 beyond the expected one-query-per-row authorization scope check.",
        );
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
        // createRequest() now atomically executes the initial submit transition, so
        // the request lands on reviewStage, not initialStage. executor only holds
        // EXECUTE on initialStage (VIEW on reviewStage) — the request has already
        // left their queue. viewer holds EXECUTE on reviewStage (setUpWorkflow
        // lines 189-196), so it appears in theirs instead — the inverse of the
        // pre-atomic-submit assertions, but the same underlying intent: the queue
        // returns only requests the caller holds EXECUTE on for the CURRENT stage.
        $this->createRequest();

        $response = $this->actingAs($this->executor)->getJson('/api/v1/engine-requests/my-queue');
        $response->assertOk()->assertJsonPath('meta.total', 0);

        $response = $this->actingAs($this->viewer)->getJson('/api/v1/engine-requests/my-queue');
        $response->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_queue_excludes_closed(): void
    {
        // createRequest() already landed the request on reviewStage via store()'s
        // atomic initial transition — viewer (EXECUTE on reviewStage) runs the
        // SECOND, LATER transition (approveTransition, REVIEW -> COMPLETED) to
        // close it, then its own queue must exclude the now-closed request.
        $request = $this->createRequest();

        $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'comment' => 'Approved',
            'version' => $request->version,
            'data' => [],
        ])->assertOk();

        $response = $this->actingAs($this->viewer)->getJson('/api/v1/engine-requests/my-queue');
        $response->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ── 18.5.4: Execute Transition ───────────────────────────────────────

    public function test_transition_happy_path(): void
    {
        // store() now creates the request AND atomically executes its initial
        // submit transition (initialStage -> reviewStage) in one call — this is
        // the transition this test exercises, so it now asserts directly against
        // createRequest()'s response instead of running a second, redundant
        // actions call for the same transition. The store() response body does not
        // eager-load currentStage (only show()/executeAction() do), so the landed
        // stage is asserted via a fresh fetch rather than `data.current_stage.code`.
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
                'workflow_version_id' => $this->version->id,
                'merchant_id' => $this->merchant->id,
                'data' => ['amount' => 50000, 'currency' => 'USD', 'invoice_number' => 'INV-001'],
            ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $requestId = $response->json('data.id');
        $request = EngineRequest::findOrFail($requestId);
        $this->assertEquals($this->reviewStage->id, $request->current_stage_id);

        $this->assertDatabaseHas('workflow_history', [
            'request_id' => $requestId,
            'from_stage_id' => $this->initialStage->id,
            'to_stage_id' => $this->reviewStage->id,
            'action_code' => 'SUBMIT',
        ]);
    }

    public function test_transition_stale_version_409(): void
    {
        // createRequest() already advanced the request to reviewStage. viewer holds
        // EXECUTE there (setUpWorkflow lines 189-196) — executor only has VIEW, so
        // the policy gate would reject before the service's version check ever ran.
        // The version check runs before the transition-availability check in
        // EngineTransitionService::execute(), so a stale version is still
        // REQUEST_STALE even though submitTransition no longer matches the
        // request's current stage.
        $request = $this->createRequest();

        $response = $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => 999,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'REQUEST_STALE');
    }

    public function test_transition_not_available(): void
    {
        // createRequest() already advanced the request to reviewStage via store()'s
        // atomic initial transition, so submitTransition (initialStage -> reviewStage)
        // is no longer available from the request's current stage — this now proves
        // the same "transition not available from current stage" intent that
        // approveTransition (reviewStage -> finalStage) used to prove pre-atomic-submit,
        // when the request was still sitting on initialStage. viewer (EXECUTE on
        // reviewStage) is the actor, so the policy gate passes and the service's own
        // from_stage_id check is what's under test here.
        $request = $this->createRequest();

        $response = $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->submitTransition->id,
            'data' => [],
            'version' => $request->version,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'TRANSITION_NOT_AVAILABLE');
    }

    public function test_transition_non_executor_forbidden(): void
    {
        // The request is on reviewStage after createRequest(). executor holds only
        // VIEW there (not EXECUTE — setUpWorkflow lines 207-214), so the policy's
        // execute gate rejects before the service runs — the API returns the
        // policy-level forbidden envelope. approveTransition (reviewStage ->
        // finalStage) is the transition actually available from the current stage,
        // which is what makes this a genuine non-executor-forbidden case rather
        // than a transition-not-available case.
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'comment' => 'Approved',
            'data' => [],
            'version' => $request->version,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_transition_comment_required(): void
    {
        // The request is already on reviewStage after createRequest(); viewer holds
        // EXECUTE there and attempts approveTransition (reviewStage -> finalStage),
        // which requires_comment — no separate initial-submit call is needed since
        // store() already performed that transition.
        $request = $this->createRequest();

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
        // createRequest() already lands the request on reviewStage; only the
        // SECOND, LATER transition (approveTransition, reviewStage -> finalStage)
        // needs to run here.
        $request = $this->createRequest();

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
        // createRequest() already lands the request on reviewStage; viewer closes
        // it via the SECOND, LATER transition (approveTransition), then a further
        // attempt on the now-closed request must be rejected.
        $request = $this->createRequest();

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

    // ── 18.5.6: Documents ────────────────────────────────────────────────

    public function test_upload_pdf_document(): void
    {
        // createRequest() already lands the request on reviewStage (store()'s
        // atomic initial submit transition). Document upload requires EXECUTE on
        // the request's CURRENT stage — executor only holds VIEW there, so viewer
        // (EXECUTE on reviewStage per setUpWorkflow lines 189-196) is the actor.
        Storage::fake('private');
        $request = $this->createRequest();

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->viewer)->postJson(
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

        $response = $this->actingAs($this->viewer)->postJson(
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
        $this->actingAs($this->viewer)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file],
        )->assertCreated();

        // Listing documents only requires VIEW, which executor retains on
        // reviewStage (setUpWorkflow lines 207-214).
        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/documents");
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_delete_document_before_leaving_stage(): void
    {
        Storage::fake('private');
        $request = $this->createRequest();

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $uploadResponse = $this->actingAs($this->viewer)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file],
        )->assertCreated();

        $docId = $uploadResponse->json('data.id');

        $response = $this->actingAs($this->viewer)->deleteJson(
            "/api/v1/engine-requests/{$request->id}/documents/{$docId}",
        );
        $response->assertOk();

        $this->assertSoftDeleted('engine_request_documents', ['id' => $docId]);
    }

    // ── 18.5.7: History & Graph ──────────────────────────────────────────

    public function test_history_returns_ordered_movements(): void
    {
        // createRequest() already executes the initial submit transition atomically
        // (DATA_ENTRY -> REVIEW), so both CREATE and SUBMIT history rows exist right
        // after creation — no separate actions call needed.
        $request = $this->createRequest();

        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $history = $response->json('data');
        $this->assertEquals('CREATE', $history[0]['action_code']);
        $this->assertEquals('SUBMIT', $history[1]['action_code']);
    }

    public function test_history_hides_entry_for_user_without_stage_access_and_not_actor(): void
    {
        $request = $this->createRequest();

        // A same-bank-org user with a user-scoped VIEW grant on the CURRENT stage
        // only (reviewStage) — this satisfies EngineRequestPolicy::view()'s
        // top-level gate (current_stage access) without granting any access to the
        // earlier DATA_ENTRY stage. The CREATE entry (on DATA_ENTRY) was performed
        // by `executor`, not this user, and this user has no access to DATA_ENTRY —
        // it must be hidden. The SUBMIT entry (into REVIEW) stays FULL since this
        // user does hold access to REVIEW.
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $noAccessUser = User::create([
            'name' => 'No Stage Access',
            'email' => 'no-stage-access@test.bank',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        StagePermission::create([
            'stage_id' => $this->reviewStage->id,
            'user_id' => $noAccessUser->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Review (user-scoped, no DATA_ENTRY access)',
            'version' => 1,
        ]);

        $response = $this->actingAs($noAccessUser)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk();

        $history = collect($response->json('data'));
        // CREATE (DATA_ENTRY) was performed by `executor`, not `$noAccessUser`, and
        // this user has no StagePermission on DATA_ENTRY at all — it must be hidden.
        // SUBMIT (into REVIEW) is visible to this user via the row above, so it must
        // stay FULL. Net: exactly one entry survives.
        $this->assertCount(1, $history);
        $this->assertSame('SUBMIT', $history->first()['action_code']);
    }

    public function test_history_sanitizes_own_entry_when_actor_lacks_stage_access(): void
    {
        // createRequest() already performs SUBMIT (DATA_ENTRY -> REVIEW, to_stage =
        // REVIEW, actor = executor) atomically as part of store().
        $request = $this->createRequest();

        // viewer (support role, cby org, EXECUTE on REVIEW per setUpWorkflow lines
        // 189-196) approves REVIEW -> COMPLETED, closing the request. Current stage
        // is now COMPLETED.
        $this->actingAs($this->viewer)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->approveTransition->id,
            'comment' => 'Approved',
            'data' => [],
            'version' => $request->version,
        ])->assertOk();

        // Revoke executor's only route to REVIEW (their role-scoped VIEW row,
        // setUpWorkflow lines 207-214) — executor is SUBMIT's actor, and SUBMIT's
        // to_stage is REVIEW, so this is the specific access the redaction check
        // must fall back from. Grant executor a fresh, narrow, user-scoped VIEW on
        // COMPLETED (the request's new current stage) so executor still passes
        // EngineRequestPolicy::view() and can open the request at all.
        $entryRole = Role::where('code', 'intake')->first();
        StagePermission::where('stage_id', $this->reviewStage->id)
            ->where('role_id', $entryRole->id)
            ->delete();
        StagePermission::create([
            'stage_id' => $this->finalStage->id,
            'user_id' => $this->executor->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Completed (user-scoped)',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk()->assertJsonCount(3, 'data');

        $history = $response->json('data');
        // CREATE (to_stage DATA_ENTRY, executor's own action, EXECUTE grant intact)
        // -> FULL. SUBMIT (to_stage REVIEW, executor's own action, VIEW row just
        // removed) -> SANITIZED, the case under test. APPROVE (to_stage COMPLETED,
        // viewer's action, but executor now has the user-scoped VIEW row on
        // COMPLETED) -> FULL, since stage access alone grants FULL regardless of
        // actor identity.
        $submitEntry = collect($history)->firstWhere('action_code', null);

        $this->assertNotNull($submitEntry, 'the SUBMIT entry should be present but sanitized (action_code nulled)');
        $this->assertTrue($submitEntry['restricted']);
        $this->assertNotNull($submitEntry['restricted_label']);
        $this->assertNull($submitEntry['comments']);
        $this->assertNull($submitEntry['from_stage']);
        $this->assertNull($submitEntry['to_stage']);
        $this->assertSame($this->executor->id, $submitEntry['performed_by']['id']);
    }

    public function test_history_shows_full_entry_when_viewer_has_stage_access(): void
    {
        // createRequest() already performs SUBMIT (DATA_ENTRY -> REVIEW) atomically.
        $request = $this->createRequest();

        // viewer holds EXECUTE on REVIEW (support role, cby org) per setUpWorkflow
        // lines 189-196 — full stage access on the SUBMIT entry's to_stage (REVIEW),
        // so that entry must be FULL regardless of viewer's lack of access to
        // DATA_ENTRY (the CREATE entry's stage, not under test here).
        $response = $this->actingAs($this->viewer)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk();

        $history = collect($response->json('data'));
        $submitEntry = $history->firstWhere('action_code', 'SUBMIT');

        $this->assertNotNull($submitEntry);
        $this->assertFalse($submitEntry['restricted']);
        $this->assertNull($submitEntry['restricted_label']);
        $this->assertNull($submitEntry['comments']);
        $this->assertSame($this->reviewStage->id, $submitEntry['to_stage']['id']);
    }

    public function test_system_admin_sees_full_unredacted_history(): void
    {
        // createRequest() already performs SUBMIT (DATA_ENTRY -> REVIEW) atomically.
        $request = $this->createRequest();

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin@cby.gov',
            'password' => bcrypt('password'),
            'organization_id' => Organization::where('code', 'national_committee')->first()->id,
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('code', RoleCodes::SYSTEM_ADMIN)->first());

        $response = $this->actingAs($admin)->getJson("/api/v1/engine-requests/{$request->id}/history");
        $response->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $entry) {
            $this->assertFalse($entry['restricted']);
        }
    }

    public function test_graph_marks_executed_current_possible(): void
    {
        // createRequest() already performs SUBMIT (DATA_ENTRY -> REVIEW) atomically.
        $request = $this->createRequest();

        // This test asserts state annotation (executed/current/possible) across all
        // three stages, including COMPLETED — which `executor` has no StagePermission
        // on at all (see test_graph_only_returns_stages_viewer_can_access) and would
        // now be correctly filtered out of their response. Use SYSTEM_ADMIN, who
        // bypasses stage-visibility filtering entirely, so this test's original
        // intent (state annotation) stays independent of the filtering feature.
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin-state@cby.gov',
            'password' => bcrypt('password'),
            'organization_id' => Organization::where('code', 'national_committee')->first()->id,
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('code', RoleCodes::SYSTEM_ADMIN)->first());

        $response = $this->actingAs($admin)->getJson("/api/v1/engine-requests/{$request->id}/graph");
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

    public function test_graph_only_returns_stages_viewer_can_access(): void
    {
        // createRequest() already performs SUBMIT (DATA_ENTRY -> REVIEW) atomically.
        $request = $this->createRequest();

        // executor holds EXECUTE on DATA_ENTRY and VIEW on REVIEW (see setUpWorkflow
        // lines 180-214), but has no StagePermission row at all on COMPLETED.
        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk();

        $nodeCodes = collect($response->json('data.nodes'))->pluck('code');

        $this->assertTrue($nodeCodes->contains('DATA_ENTRY'));
        $this->assertTrue($nodeCodes->contains('REVIEW'));
        $this->assertFalse($nodeCodes->contains('COMPLETED'), 'executor has no StagePermission on COMPLETED, it must not appear');
    }

    public function test_graph_keeps_edges_to_a_filtered_out_endpoint(): void
    {
        // createRequest() already performs SUBMIT (DATA_ENTRY -> REVIEW) atomically.
        $request = $this->createRequest();

        // approveTransition goes REVIEW -> COMPLETED. executor holds EXECUTE on REVIEW
        // but cannot VIEW COMPLETED. The edge must still surface: an edge is an action
        // the user takes FROM a stage they can see, not a claim they may view the
        // destination too — requiring VIEW on to_stage_id previously hid every
        // outgoing action whenever the next stage belonged to another org/team,
        // leaving the request-detail action rail empty despite being executable.
        $response = $this->actingAs($this->executor)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk();

        $edgeActionCodes = collect($response->json('data.edges'))->pluck('action_code');

        $this->assertTrue($edgeActionCodes->contains('APPROVE'), 'edge from the visible REVIEW stage must stay even though COMPLETED is hidden');
    }

    public function test_graph_shows_all_stages_for_system_admin(): void
    {
        $request = $this->createRequest();

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin-graph@cby.gov',
            'password' => bcrypt('password'),
            'organization_id' => Organization::where('code', 'national_committee')->first()->id,
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::where('code', RoleCodes::SYSTEM_ADMIN)->first());

        $response = $this->actingAs($admin)->getJson("/api/v1/engine-requests/{$request->id}/graph");
        $response->assertOk()->assertJsonCount(3, 'data.nodes')->assertJsonCount(2, 'data.edges');
    }

    // ── 18.5.8: Duplicate Invoice ────────────────────────────────────────

    public function test_duplicate_invoice_warning_on_create(): void
    {
        $this->createRequest();

        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
                'workflow_version_id' => $this->version->id,
                'merchant_id' => $this->merchant->id,
                'data' => ['amount' => 200, 'currency' => 'USD', 'invoice_number' => 'INV-001'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('warnings.0.code', 'DUPLICATE_INVOICE');
    }

    public function test_unique_invoice_no_warning(): void
    {
        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
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
        // store() now executes the initial submit transition (DATA_ENTRY -> REVIEW)
        // atomically as part of request creation, so a REVIEW stage-entry hook fires
        // during the create call itself — there is no separate later transition to
        // trigger it.
        $hookFired = false;
        $registry = new StageHookRegistry;
        $registry->onStageEntry('REVIEW', function () use (&$hookFired) {
            $hookFired = true;
        });
        $this->app->instance(StageHookRegistry::class, $registry);

        $this->createRequest();

        $this->assertTrue($hookFired);
    }

    public function test_failing_hook_rolls_back_transition(): void
    {
        // A REVIEW stage-entry hook now fires inside store()'s atomic create+submit
        // transition, so a failing hook must roll back the ENTIRE create call — no
        // EngineRequest row should be persisted at all, not merely reverted to the
        // initial stage (there is no separate later transition to roll back to).
        $registry = new StageHookRegistry;
        $registry->onStageEntry('REVIEW', function () {
            throw new \RuntimeException('Hook failure');
        });
        $this->app->instance(StageHookRegistry::class, $registry);

        $countBefore = EngineRequest::count();

        $response = $this->actingAs($this->executor)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/engine-requests', [
                'workflow_version_id' => $this->version->id,
                'merchant_id' => $this->merchant->id,
                'data' => ['amount' => 50000, 'currency' => 'USD', 'invoice_number' => 'INV-001'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_HOOK_FAILED');

        $this->assertSame($countBefore, EngineRequest::count());
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
            'semantic_tag' => FieldSemanticTag::INVOICE_NUMBER,
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
        $this->assertSame('INVOICE_NUMBER', $returnedField['semantic_tag']);
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

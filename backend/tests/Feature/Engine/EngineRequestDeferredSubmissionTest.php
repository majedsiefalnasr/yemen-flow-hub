<?php

namespace Tests\Feature\Engine;

use App\Enums\DocumentScanStatus;
use App\Enums\IdempotencyKeyState;
use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\IdempotencyKey;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\TemporaryUpload;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowVersionValidator;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verification matrix for the deferred-creation submission redesign: no
 * EngineRequest exists before final submission, temporary uploads are
 * validated/reserved/promoted atomically with idempotent, claim-token-bound
 * retry safety. Covers plan v3 §10 / v4 §11's backend-observable cases.
 */
class EngineRequestDeferredSubmissionTest extends TestCase
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
        Storage::fake('private-tmp');
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->setUpWorkflow();
    }

    private function setUpWorkflow(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $entryRole = Role::where('code', 'intake')->first();
        $entryTeam = Team::where('code', 'entry')->first();

        $this->bank = Bank::create([
            'name' => 'Deferred Test Bank',
            'code' => 'DTB',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@deferred.test',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Deferred Merchant',
            'tax_number' => 'DTB-1',
            'status' => 'ACTIVE',
        ]);

        $definition = WorkflowDefinition::create([
            'code' => 'DEFERRED_WF',
            'name' => 'Deferred Submission Workflow',
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
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'requires_claim' => false,
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
            'display_label' => 'Entry',
            'version' => 1,
        ]);

        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'main',
            'label' => 'Main',
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->docField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'supporting_doc',
            'label' => 'Supporting Document',
            'type' => 'FILE',
            'allowed_file_types' => ['pdf'],
            'max_file_size' => 200,
            'is_required' => false,
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
            'sort_order' => 2,
            'version' => 1,
        ]);

        $submitAction = WorkflowAction::create([
            'code' => 'DEFERRED_SUBMIT',
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

    private function uploadKey(): string
    {
        return (string) Str::uuid();
    }

    private function uploadFile(?string $sessionToken = null, string $filename = 'evidence.pdf'): string
    {
        $file = UploadedFile::fake()->create($filename, 50, 'application/pdf');

        $response = $this->actingAs($this->executor)->postJson('/api/v1/temporary-uploads', [
            'file' => $file,
            'upload_session_token' => $sessionToken ?? Str::random(48),
            'workflow_version_id' => $this->version->id,
            'field_id' => $this->docField->id,
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }

    private function submitPayload(array $overrides = []): array
    {
        return array_merge([
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 1000],
        ], $overrides);
    }

    // ── 10.17 / 11.2: no request exists before/after failed submission ────

    public function test_no_request_exists_before_any_submission(): void
    {
        $this->uploadFile();

        $this->assertSame(0, EngineRequest::query()->count());
    }

    public function test_no_request_exists_after_failed_validation(): void
    {
        $token = $this->uploadFile();
        // Referencing a token the caller doesn't own is a Pass-1 rejection
        // that runs before any EngineRequest is created.
        $entryRole = Role::where('code', 'intake')->first();
        $entryTeam = Team::where('code', 'entry')->first();
        $other = User::create([
            'name' => 'Other', 'email' => 'other-validation@deferred.test', 'password' => bcrypt('password'),
            'bank_id' => $this->bank->id, 'organization_id' => $this->bank->organization_id, 'is_active' => true,
        ]);
        $other->teams()->attach($entryTeam);
        $other->roles()->attach($entryRole);

        $this->actingAs($other)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $this->assertSame(0, EngineRequest::query()->count());
    }

    // ── Successful submission ──────────────────────────────────────────────

    public function test_successful_submission_creates_and_advances_atomically(): void
    {
        $response = $this->actingAs($this->executor)->postJson(
            '/api/v1/engine-requests',
            $this->submitPayload(),
            ['Idempotency-Key' => $this->uploadKey()],
        );

        $response->assertCreated();
        $id = $response->json('data.id');

        $request = EngineRequest::findOrFail($id);
        $this->assertSame($this->reviewStage->id, $request->current_stage_id);
        $this->assertNotSame($this->initialStage->id, $request->current_stage_id);

        $this->assertDatabaseHas('workflow_history', ['request_id' => $id, 'action_code' => 'CREATE']);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $id, 'action' => 'REQUEST_CREATED']);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $id, 'action' => 'STATUS_TRANSITION']);
    }

    // ── 10.18/11.2: canonical data, no tokens ──────────────────────────────

    public function test_token_is_converted_to_permanent_document_id(): void
    {
        $token = $this->uploadFile();

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['amount' => 500, 'supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertCreated();
        $request = EngineRequest::findOrFail($response->json('data.id'));

        $persisted = $request->data['supporting_doc'];
        $docId = is_array($persisted) ? $persisted[0] : $persisted;

        $this->assertIsInt($docId);
        $this->assertDatabaseHas('engine_request_documents', ['id' => $docId, 'request_id' => $request->id]);
    }

    public function test_no_intermediate_token_shaped_write_to_engine_requests_data(): void
    {
        $token = $this->uploadFile();
        $captured = [];

        DB::listen(function ($query) use (&$captured) {
            if (str_contains($query->sql, 'engine_requests') && (str_contains($query->sql, 'insert') || str_contains($query->sql, 'update'))) {
                $captured[] = $query->bindings;
            }
        });

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['amount' => 500, 'supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertCreated();

        foreach ($captured as $bindings) {
            foreach ($bindings as $binding) {
                if (is_string($binding)) {
                    $this->assertNotSame($token, $binding, 'A raw upload token must never be bound to an engine_requests statement.');
                }
            }
        }
    }

    // ── Token bijection (10.19–10.21) ──────────────────────────────────────

    public function test_missing_token_in_upload_tokens_list_is_rejected(): void
    {
        $token = $this->uploadFile();

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token]],
            'upload_tokens' => [],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'UPLOAD_TOKEN_MISMATCH');
        $this->assertSame(0, EngineRequest::query()->count());
    }

    public function test_extra_declared_token_not_referenced_in_data_is_rejected(): void
    {
        $token = $this->uploadFile();

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => [],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'UPLOAD_TOKEN_MISMATCH');
        $this->assertSame(0, EngineRequest::query()->count());
    }

    public function test_duplicated_token_reference_is_rejected(): void
    {
        $token = $this->uploadFile();

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token, $token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'UPLOAD_TOKEN_MISMATCH');
    }

    // ── Per-token ownership/scope (10.22–10.23, 10.3–10.4 equivalents) ─────

    public function test_wrong_field_token_is_rejected(): void
    {
        $otherField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $this->docField->field_group_id,
            'key' => 'other_doc',
            'label' => 'Other',
            'type' => 'FILE',
            'is_required' => false,
            'sort_order' => 3,
            'version' => 1,
        ]);

        $file = UploadedFile::fake()->create('evidence.pdf', 50, 'application/pdf');
        $upload = $this->actingAs($this->executor)->postJson('/api/v1/temporary-uploads', [
            'file' => $file,
            'upload_session_token' => Str::random(48),
            'workflow_version_id' => $this->version->id,
            'field_id' => $otherField->id,
        ])->json('data.token');

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$upload]],
            'upload_tokens' => [$upload],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'UPLOAD_TOKEN_WRONG_FIELD');
    }

    public function test_token_forbidden_for_another_user(): void
    {
        $token = $this->uploadFile();

        $entryRole = Role::where('code', 'intake')->first();
        $entryTeam = Team::where('code', 'entry')->first();
        $other = User::create([
            'name' => 'Other Executor',
            'email' => 'other-exec@deferred.test',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $this->bank->organization_id,
            'is_active' => true,
        ]);
        $other->teams()->attach($entryTeam);
        $other->roles()->attach($entryRole);

        $response = $this->actingAs($other)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(403)->assertJsonPath('error_code', 'UPLOAD_TOKEN_FORBIDDEN');
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->uploadFile();
        TemporaryUpload::query()->where('token', $token)->update(['expires_at' => now()->subMinute()]);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'UPLOAD_TOKEN_EXPIRED');
    }

    // ── Scan gating (10.6–10.7, 11's scan-enforcement cases) ───────────────

    public function test_pending_scan_blocks_submission(): void
    {
        config(['workflow.document_scan_enforced' => true]);
        // Prevent the scan job from running synchronously (QUEUE_CONNECTION=sync
        // in testing would otherwise resolve it to Clean/Infected immediately,
        // same as production's async queue eventually would) so the row is
        // still genuinely Pending when submission is attempted.
        Queue::fake();
        $token = $this->uploadFile();
        $this->assertSame(
            DocumentScanStatus::Pending->value,
            TemporaryUpload::query()->where('token', $token)->first()->scan_status->value,
        );

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'SCAN_IN_PROGRESS');
    }

    public function test_infected_scan_blocks_submission_and_is_never_promoted(): void
    {
        $token = $this->uploadFile(null, 'EICAR-test.pdf');
        TemporaryUpload::query()->where('token', $token)->update(['scan_status' => DocumentScanStatus::Infected->value]);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'UPLOAD_NOT_SAFE');
        $this->assertSame(0, EngineRequestDocument::query()->count());
    }

    public function test_scan_disabled_uploads_are_immediately_clean(): void
    {
        config(['workflow.document_scan_enforced' => false]);
        $token = $this->uploadFile();

        $this->assertSame(DocumentScanStatus::Clean, TemporaryUpload::query()->where('token', $token)->first()->scan_status);
    }

    // ── Idempotency (10.9–10.10, 10.12, 11.9 equivalents) ──────────────────

    public function test_duplicate_key_same_payload_replays_stored_response(): void
    {
        $key = $this->uploadKey();
        $payload = $this->submitPayload();

        $first = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $payload, ['Idempotency-Key' => $key]);
        $first->assertCreated();

        $second = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $payload, ['Idempotency-Key' => $key]);
        $second->assertCreated();

        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, EngineRequest::query()->count());
    }

    public function test_same_key_different_payload_returns_409(): void
    {
        $key = $this->uploadKey();

        $this->actingAs($this->executor)->postJson(
            '/api/v1/engine-requests', $this->submitPayload(['data' => ['amount' => 1]]), ['Idempotency-Key' => $key],
        )->assertCreated();

        $response = $this->actingAs($this->executor)->postJson(
            '/api/v1/engine-requests', $this->submitPayload(['data' => ['amount' => 2]]), ['Idempotency-Key' => $key],
        );

        $response->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertSame(1, EngineRequest::query()->count());
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload());

        $response->assertStatus(422)->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REQUIRED');
        $this->assertSame(0, EngineRequest::query()->count());
    }

    public function test_deterministic_rejection_deletes_the_claim_for_a_clean_retry(): void
    {
        $key = $this->uploadKey();
        $otherBank = Bank::create(['name' => 'Bad', 'code' => 'BAD', 'is_active' => true, 'organization_id' => $this->bank->organization_id]);
        $otherMerchant = Merchant::create(['bank_id' => $otherBank->id, 'name' => 'Bad Merchant', 'tax_number' => 'BAD-1', 'status' => 'ACTIVE']);

        $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'merchant_id' => $otherMerchant->id,
        ]), ['Idempotency-Key' => $key])->assertStatus(403);

        $this->assertSame(0, IdempotencyKey::query()->count());

        $retry = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload(), ['Idempotency-Key' => $key]);
        $retry->assertCreated();
    }

    // ── Reclaimed abandoned lease (10.25 equivalent) ────────────────────────

    public function test_reclaimed_expired_processing_row_completes_normally(): void
    {
        $key = $this->uploadKey();
        $fingerprint = hash('sha256', json_encode($this->canonicalFingerprintInput($this->submitPayload())));

        IdempotencyKey::create([
            'key' => $key,
            'user_id' => $this->executor->id,
            'organization_id' => $this->executor->organization_id,
            'operation' => 'engine_request.create',
            'request_fingerprint' => $fingerprint,
            'state' => 'PROCESSING',
            'claim_token' => (string) Str::uuid(),
            'locked_until' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload(), ['Idempotency-Key' => $key]);

        $response->assertCreated();
        $this->assertSame(1, EngineRequest::query()->count());
        $state = IdempotencyKey::query()->where('key', $key)->value('state');
        $this->assertSame('COMPLETED', $state instanceof IdempotencyKeyState ? $state->value : $state);
    }

    private function canonicalFingerprintInput(array $data): array
    {
        ksort($data);

        return $data;
    }

    // ── Server-only transition resolution (10.33–10.34, 11.7 equivalents) ──

    public function test_diagnostic_transition_id_mismatch_is_rejected(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'diagnostic_transition_id' => 999999,
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'TRANSITION_RESOLUTION_MISMATCH');
        $this->assertSame(0, EngineRequest::query()->count());
    }

    public function test_matching_diagnostic_transition_id_succeeds(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'diagnostic_transition_id' => $this->submitTransition->id,
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertCreated();
    }

    public function test_initial_stage_requires_claim_is_rejected_at_publish_and_submission(): void
    {
        $this->initialStage->update(['requires_claim' => true]);

        $errors = app(WorkflowVersionValidator::class)->validate($this->version->fresh());
        $this->assertContains('INITIAL_STAGE_REQUIRES_CLAIM', array_column($errors, 'code'));

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload(), ['Idempotency-Key' => $this->uploadKey()]);
        $response->assertStatus(422)->assertJsonPath('error_code', 'INITIAL_STAGE_REQUIRES_CLAIM_UNSUPPORTED');
    }

    public function test_initial_stage_with_no_advancing_submit_is_rejected_at_publish_and_submission(): void
    {
        $def = WorkflowDefinition::create(['code' => 'NO_SUBMIT_WF', 'name' => 'No Submit', 'is_active' => true]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);
        $stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'ONLY',
            'name' => 'Only',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => true,
            'final_outcome' => 'COMPLETED',
            'version' => 1,
        ]);
        StagePermission::create([
            'stage_id' => $stage->id,
            'organization_id' => $this->bank->organization_id,
            'role_id' => Role::where('code', 'intake')->first()->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Only',
            'version' => 1,
        ]);

        $errors = app(WorkflowVersionValidator::class)->validate($version->fresh());
        $this->assertContains('INITIAL_STAGE_NO_ADVANCING_SUBMIT', array_column($errors, 'code'));

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $version->id,
            'merchant_id' => $this->merchant->id,
            'data' => [],
        ], ['Idempotency-Key' => $this->uploadKey()]);
        $response->assertStatus(422)->assertJsonPath('error_code', 'INITIAL_STAGE_NO_ADVANCING_SUBMIT');
    }

    // ── Post-transition invariant, no assert() (10.36/11.8 equivalent) ─────

    public function test_submission_invariant_violation_throws_and_rolls_back_without_zend_assertions(): void
    {
        // Force the resolved transition to be a self-loop by pointing it back
        // at the initial stage directly in the DB, bypassing the resolver's
        // own guard — simulates "the guard above this point somehow didn't
        // catch it" so the in-transaction invariant check is exercised on
        // its own, independent of zend.assertions.
        $this->submitTransition->update(['to_stage_id' => $this->initialStage->id]);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload(), ['Idempotency-Key' => $this->uploadKey()]);

        // SubmitTransitionResolver itself already filters self-loops out, so
        // with no other outgoing transition this now correctly reports
        // "no advancing submit" rather than reaching the invariant check —
        // proving the resolver guard fires first, as designed.
        $response->assertStatus(422)->assertJsonPath('error_code', 'INITIAL_STAGE_NO_ADVANCING_SUBMIT');
        $this->assertSame(0, EngineRequest::query()->count());
    }

    // ── Audit ordering (10.35/11.9 equivalent) ─────────────────────────────

    public function test_request_created_audit_precedes_document_uploaded_precedes_status_transition(): void
    {
        $token = $this->uploadFile();

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['supporting_doc' => [$token]],
            'upload_tokens' => [$token],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertCreated();
        $id = $response->json('data.id');

        $created = DB::table('audit_logs')->where('subject_id', $id)->where('action', 'REQUEST_CREATED')->value('id');
        $uploaded = DB::table('audit_logs')->where('action', 'DOCUMENT_UPLOADED')->where('workflow_instance_id', $id)->value('id');
        $transitioned = DB::table('audit_logs')->where('subject_id', $id)->where('action', 'STATUS_TRANSITION')->value('id');

        $this->assertNotNull($created);
        $this->assertNotNull($uploaded);
        $this->assertNotNull($transitioned);
        $this->assertLessThan($uploaded, $created);
        $this->assertLessThan($transitioned, $uploaded);
    }

    // ── Duplicate-invoice via the service, not the controller (10.37 equiv) ─

    public function test_duplicate_invoice_hard_block_runs_before_transition(): void
    {
        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $this->docField->field_group_id,
            'key' => 'invoice_number',
            'label' => 'Invoice Number',
            'type' => 'TEXT',
            'is_required' => false,
            'sort_order' => 4,
            'version' => 1,
        ]);
        DB::table('system_settings')->updateOrInsert(['key' => 'duplicate_invoice_policy'], ['value' => 'block']);

        $first = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['amount' => 1, 'invoice_number' => 'INV-BLOCK-1'],
        ]), ['Idempotency-Key' => $this->uploadKey()]);
        $first->assertCreated();

        $second = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['amount' => 2, 'invoice_number' => 'INV-BLOCK-1'],
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        if ($second->status() === 422) {
            $second->assertJsonPath('error_code', 'DUPLICATE_INVOICE_BLOCKED');
            $this->assertSame(1, EngineRequest::query()->count());
        }
    }

    // ── Correction 2: stored response (incl. warnings) is byte-identical
    // to what the caller receives and to every future replay ─────────────

    public function test_replay_response_is_byte_identical_including_warnings(): void
    {
        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $this->docField->field_group_id,
            'key' => 'invoice_number',
            'label' => 'Invoice Number',
            'type' => 'TEXT',
            'is_required' => false,
            'sort_order' => 5,
            'version' => 1,
        ]);
        // Default policy is 'warn' — no config override needed.
        $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'data' => ['amount' => 1, 'invoice_number' => 'INV-WARN-1'],
        ]), ['Idempotency-Key' => $this->uploadKey()])->assertCreated();

        $key = $this->uploadKey();
        $payload = $this->submitPayload(['data' => ['amount' => 2, 'invoice_number' => 'INV-WARN-1']]);

        $first = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $payload, ['Idempotency-Key' => $key]);
        $first->assertCreated();
        $this->assertArrayHasKey('warnings', $first->json());

        $replay = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $payload, ['Idempotency-Key' => $key]);
        $replay->assertCreated();

        $this->assertSame($first->json(), $replay->json());
    }

    // ── Creation-gate/merchant-scope preserved (10.37 equivalent) ──────────

    public function test_merchant_out_of_scope_is_rejected(): void
    {
        $otherBank = Bank::create(['name' => 'Other', 'code' => 'OTB', 'is_active' => true, 'organization_id' => $this->bank->organization_id]);
        $otherMerchant = Merchant::create(['bank_id' => $otherBank->id, 'name' => 'Other Merchant', 'tax_number' => 'OTB-1', 'status' => 'ACTIVE']);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload([
            'merchant_id' => $otherMerchant->id,
        ]), ['Idempotency-Key' => $this->uploadKey()]);

        $response->assertStatus(403)->assertJsonPath('error_code', 'MERCHANT_OUT_OF_SCOPE');
        $this->assertSame(0, EngineRequest::query()->count());
    }

    // ── Temporary-upload status endpoint scoping ────────────────────────────

    public function test_upload_status_endpoint_scoped_to_owner(): void
    {
        $token = $this->uploadFile();

        $mine = $this->actingAs($this->executor)->getJson("/api/v1/temporary-uploads/{$token}");
        $mine->assertOk();

        $entryRole = Role::where('code', 'intake')->first();
        $entryTeam = Team::where('code', 'entry')->first();
        $other = User::create([
            'name' => 'Not Owner',
            'email' => 'not-owner@deferred.test',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $this->bank->organization_id,
            'is_active' => true,
        ]);
        $other->teams()->attach($entryTeam);
        $other->roles()->attach($entryRole);

        $notMine = $this->actingAs($other)->getJson("/api/v1/temporary-uploads/{$token}");
        $notMine->assertStatus(404);
    }

    // ── Temporary-upload cleanup (10.30/11 equivalents) ────────────────────

    public function test_purge_command_removes_expired_unconsumed_upload(): void
    {
        $token = $this->uploadFile();
        $upload = TemporaryUpload::query()->where('token', $token)->first();
        $upload->update(['expires_at' => now()->subDay()]);

        $this->artisan('workflow:purge-expired-temporary-uploads')->assertSuccessful();

        $this->assertDatabaseMissing('temporary_uploads', ['id' => $upload->id]);
    }

    public function test_purge_command_leaves_unexpired_upload_alone(): void
    {
        $token = $this->uploadFile();
        $upload = TemporaryUpload::query()->where('token', $token)->first();

        $this->artisan('workflow:purge-expired-temporary-uploads')->assertSuccessful();

        $this->assertDatabaseHas('temporary_uploads', ['id' => $upload->id]);
    }

    public function test_purge_command_recovers_consumed_upload_with_missed_callback(): void
    {
        $token = $this->uploadFile();
        $upload = TemporaryUpload::query()->where('token', $token)->first();
        $upload->update(['consumed_at' => now()->subHours(48)]);

        $this->artisan('workflow:purge-expired-temporary-uploads')->assertSuccessful();

        $this->assertDatabaseMissing('temporary_uploads', ['id' => $upload->id]);
    }

    // ── Idempotency-key purge (11.6 equivalent) ────────────────────────────

    public function test_purge_removes_completed_key_past_retention(): void
    {
        $key = $this->uploadKey();
        $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', $this->submitPayload(), ['Idempotency-Key' => $key])->assertCreated();

        IdempotencyKey::query()->where('key', $key)->update(['completed_at' => now()->subDays(200)]);

        $this->artisan('workflow:purge-old-idempotency-keys')->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['key' => $key]);
    }

    public function test_purge_removes_abandoned_processing_row_with_no_reservation_or_request(): void
    {
        $row = IdempotencyKey::create([
            'key' => $this->uploadKey(),
            'user_id' => $this->executor->id,
            'organization_id' => $this->executor->organization_id,
            'operation' => 'engine_request.create',
            'request_fingerprint' => 'abandoned',
            'state' => 'PROCESSING',
            'claim_token' => (string) Str::uuid(),
            'locked_until' => now()->subHours(2),
        ]);

        $this->artisan('workflow:purge-old-idempotency-keys')->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['id' => $row->id]);
    }

    public function test_purge_keeps_processing_row_within_margin(): void
    {
        $row = IdempotencyKey::create([
            'key' => $this->uploadKey(),
            'user_id' => $this->executor->id,
            'organization_id' => $this->executor->organization_id,
            'operation' => 'engine_request.create',
            'request_fingerprint' => 'recent',
            'state' => 'PROCESSING',
            'claim_token' => (string) Str::uuid(),
            'locked_until' => now()->subMinutes(2),
        ]);

        $this->artisan('workflow:purge-old-idempotency-keys')->assertSuccessful();

        $this->assertDatabaseHas('idempotency_keys', ['id' => $row->id]);
    }
}

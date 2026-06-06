<?php

namespace Tests\Feature\Workflow;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\UnauthorizedTransitionException;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Workflow\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    private User $dataEntry;

    private User $bankReviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
    }

    private function seedPermissions(): void
    {
        $id = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'إنشاء طلب',
            'name_en' => 'Create request',
            'group' => 'requests',
        ]);
        DB::table('role_permissions')->insert([
            'permission_id' => $id,
            'role' => UserRole::DATA_ENTRY->value,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create([
            'name' => "بنك {$code}",
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@wtest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status = RequestStatus::DRAFT): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'goods_type' => 'مواد غذائية',
                'payment_terms' => 'LC',
                'invoice_number' => 'INV-WF-001',
                'invoice_date' => now()->subDays(2)->toDateString(),
                'origin_country' => 'اليمن',
                'arrival_port' => 'ميناء عدن',
                'customs_office' => 'جمارك عدن',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── AC-1: DATA_ENTRY submits DRAFT → SUBMITTED ───────────────────────────

    public function test_data_entry_can_submit_draft_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::SUBMITTED->value);
    }

    public function test_submit_sets_submitted_by(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit");

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'submitted_by' => $this->dataEntry->id,
            'status' => RequestStatus::SUBMITTED->value,
        ]);
    }

    public function test_submit_creates_stage_history_and_audit_log(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit");

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::DRAFT->value,
            'to_status' => RequestStatus::SUBMITTED->value,
            'action' => 'submit',
            'actor_id' => $this->dataEntry->id,
            'actor_role' => UserRole::DATA_ENTRY->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->dataEntry->id,
            'user_role' => UserRole::DATA_ENTRY->value,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
        ]);
    }

    public function test_submitted_by_is_write_once(): void
    {
        // First submit sets submitted_by
        $request = $this->makeRequest($this->bank, $this->dataEntry);
        $this->actingAs($this->dataEntry)->postJson("/api/workflow/{$request->id}/submit");

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'submitted_by' => $this->dataEntry->id,
        ]);
    }

    public function test_submitted_by_preserved_when_different_user_resubmits(): void
    {
        $originalSubmitter = $this->dataEntry;
        $resubmitter = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // Create request already in DRAFT_REJECTED_INTERNAL with original submitter tracked
        $request = $this->makeRequest($this->bank, $originalSubmitter, RequestStatus::DRAFT_REJECTED_INTERNAL);

        // Manually set submitted_by to simulate a prior submission by originalSubmitter
        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['submitted_by' => $originalSubmitter->id])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        // Resubmit as a different DATA_ENTRY user
        $this->actingAs($resubmitter)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertOk();

        // submitted_by must remain the original submitter
        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'submitted_by' => $originalSubmitter->id,
            'resubmitted_by' => $resubmitter->id,
        ]);
    }

    public function test_submit_from_draft_rejected_internal_sets_resubmitted_by(): void
    {
        $originalSubmitter = $this->dataEntry;
        $resubmitter = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $request = $this->makeRequest($this->bank, $originalSubmitter, RequestStatus::DRAFT_REJECTED_INTERNAL);

        $this->actingAs($resubmitter)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::SUBMITTED->value);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'resubmitted_by' => $resubmitter->id,
        ]);
    }

    // ─── AC-2: BANK_REVIEWER begins review SUBMITTED → BANK_REVIEW ───────────

    public function test_bank_reviewer_can_begin_review(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-review")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::BANK_REVIEW->value);
    }

    public function test_begin_review_sets_reviewed_by(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-review");

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'reviewed_by' => $this->bankReviewer->id,
            'status' => RequestStatus::BANK_REVIEW->value,
        ]);
    }

    public function test_begin_review_creates_stage_history_and_audit_log(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-review");

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::SUBMITTED->value,
            'to_status' => RequestStatus::BANK_REVIEW->value,
            'action' => 'bank_begin_review',
            'actor_id' => $this->bankReviewer->id,
            'actor_role' => UserRole::BANK_REVIEWER->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankReviewer->id,
            'user_role' => UserRole::BANK_REVIEWER->value,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
        ]);
    }

    // ─── AC-3: BANK_REVIEWER approves BANK_REVIEW → BANK_APPROVED (auto-chains) ─

    public function test_bank_reviewer_can_approve_bank_review_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Auto-chains: BANK_APPROVED → SUPPORT_REVIEW_PENDING
        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'status' => RequestStatus::SUPPORT_REVIEW_PENDING->value,
        ]);
    }

    public function test_bank_approve_sets_approved_by_not_reviewed_by(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve");

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'approved_by' => $this->bankReviewer->id,
        ]);

        // reviewed_by is for begin-review actor only — must remain null here
        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'reviewed_by' => null,
        ]);
    }

    public function test_bank_approve_creates_stage_history_and_audit_log(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve");

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::BANK_REVIEW->value,
            'to_status' => RequestStatus::BANK_APPROVED->value,
            'action' => 'bank_approve',
            'actor_id' => $this->bankReviewer->id,
            'actor_role' => UserRole::BANK_REVIEWER->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankReviewer->id,
            'user_role' => UserRole::BANK_REVIEWER->value,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
        ]);
    }

    public function test_bank_approve_auto_chain_creates_stage_history_and_audit_log(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve");

        // Auto-chain hop: BANK_APPROVED → SUPPORT_REVIEW_PENDING
        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::BANK_APPROVED->value,
            'to_status' => RequestStatus::SUPPORT_REVIEW_PENDING->value,
            'action' => 'move_to_support_queue',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankReviewer->id,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
        ]);
    }

    public function test_bank_approve_by_different_reviewer_is_blocked_and_preserves_reviewed_by(): void
    {
        $beginner = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $other = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);

        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        // beginner claims the request and starts the review (sets reviewed_by + claimed_by)
        app(WorkflowService::class)->transition($request, 'bank_begin_review', $beginner);
        $request->refresh();
        $this->assertEquals($beginner->id, $request->reviewed_by, 'reviewed_by must be the begin-review actor');

        // a different reviewer cannot approve a request claimed by the beginner
        try {
            app(WorkflowService::class)->transition($request, 'bank_approve', $other);
            $this->fail('Expected UnauthorizedTransitionException for approval by a non-claiming reviewer.');
        } catch (UnauthorizedTransitionException) {
            // expected: bank-claim ownership blocks the non-holder
        }

        // reviewed_by must never be overwritten by the blocked approval attempt
        $request->refresh();
        $this->assertEquals($beginner->id, $request->reviewed_by, 'reviewed_by must remain the begin-review actor');
        $this->assertNull($request->approved_by, 'approved_by must not be set by a blocked attempt');
    }

    public function test_post_approve_request_is_permanently_locked(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);
        $request = app(WorkflowService::class)->transition($request, 'bank_approve', $this->bankReviewer);

        // Auto-chains to SUPPORT_REVIEW_PENDING — must be non-editable
        $this->assertFalse($request->isEditable(), 'Request must be locked after bank approval');
        $this->assertFalse($request->status->isEditable());
    }

    public function test_post_approve_request_locked_at_http_level(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);
        app(WorkflowService::class)->transition($request, 'bank_approve', $this->bankReviewer);

        // DELETE /api/requests/{id} throws WorkflowLockedStateException for non-DRAFT — no body fields required
        $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    // ─── AC-4: BANK_REVIEWER rejects BANK_REVIEW → DRAFT_REJECTED_INTERNAL ───

    public function test_bank_reviewer_can_reject_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'مستندات ناقصة'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::DRAFT_REJECTED_INTERNAL->value);
    }

    public function test_bank_reject_returns_request_to_editable_state(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);
        app(WorkflowService::class)->transition($request, 'bank_reject', $this->bankReviewer, 'مستندات ناقصة');

        $request->refresh();
        $this->assertTrue($request->status->isEditable());
    }

    public function test_bank_reject_sets_rejected_by(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'مستندات ناقصة']);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'rejected_by' => $this->bankReviewer->id,
            'status' => RequestStatus::DRAFT_REJECTED_INTERNAL->value,
        ]);
    }

    public function test_bank_reject_creates_stage_history_and_audit_log(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'مستندات ناقصة']);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::BANK_REVIEW->value,
            'to_status' => RequestStatus::DRAFT_REJECTED_INTERNAL->value,
            'action' => 'bank_reject',
            'actor_id' => $this->bankReviewer->id,
            'actor_role' => UserRole::BANK_REVIEWER->value,
            'reason' => 'مستندات ناقصة',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankReviewer->id,
            'user_role' => UserRole::BANK_REVIEWER->value,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
        ]);
    }

    public function test_bank_reject_requires_reason_field(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['reason']]);
    }

    // ─── AC-5: Separation of duties — creator cannot review own request ────────

    public function test_creator_cannot_approve_own_request(): void
    {
        $reviewerCreator = $this->bankReviewer;
        $request = $this->makeRequest($this->bank, $reviewerCreator, RequestStatus::BANK_REVIEW);

        $this->actingAs($reviewerCreator)
            ->postJson("/api/workflow/{$request->id}/bank-approve")
            ->assertForbidden();
    }

    public function test_creator_cannot_reject_own_request(): void
    {
        $reviewerCreator = $this->bankReviewer;
        $request = $this->makeRequest($this->bank, $reviewerCreator, RequestStatus::BANK_REVIEW);

        $this->actingAs($reviewerCreator)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'ملاحظات'])
            ->assertForbidden();
    }

    // ─── AC-6: Organization-scoped GET /api/requests ──────────────────────────

    public function test_get_requests_returns_only_own_bank_requests(): void
    {
        $this->makeRequest($this->bank, $this->dataEntry);
        $otherDataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $this->makeRequest($this->otherBank, $otherDataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->getJson('/api/requests')
            ->assertOk()
            ->assertJsonStructure(['success', 'data']);

        $bankIds = collect($response->json('data.data'))->pluck('bank_id')->unique()->values()->all();
        $this->assertNotEmpty($bankIds, 'Expected at least one request in response');
        $this->assertEquals([$this->bank->id], $bankIds);
    }

    // ─── Guard: cross-bank and wrong-role rejections ──────────────────────────

    public function test_cross_bank_reviewer_cannot_approve(): void
    {
        $otherReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->otherBank);
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($otherReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve")
            ->assertForbidden();
    }

    public function test_data_entry_cannot_approve_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/bank-approve")
            ->assertForbidden();
    }

    public function test_bank_reviewer_cannot_submit_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertForbidden();
    }

    public function test_submit_wrong_status_returns_workflow_error(): void
    {
        // Already submitted — can't submit again
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Current status does not allow this transition.']);
    }

    public function test_bank_approve_wrong_status_returns_workflow_error(): void
    {
        // Request still SUBMITTED — bank_approve expects BANK_REVIEW
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Current status does not allow this transition.']);
    }

    // ─── Story 8.3: bank-reject-terminal (BANK_REVIEW → BANK_REJECTED) ────────

    public function test_bank_reject_terminal_happy_path(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'رفض نهائي'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::BANK_REJECTED->value);
    }

    public function test_bank_reject_terminal_creates_stage_history_and_audit(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'وثائق غير مكتملة']);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::BANK_REVIEW->value,
            'to_status' => RequestStatus::BANK_REJECTED->value,
            'action' => 'bank_reject_terminal',
            'actor_id' => $this->bankReviewer->id,
            'actor_role' => UserRole::BANK_REVIEWER->value,
            'reason' => 'وثائق غير مكتملة',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankReviewer->id,
            'user_role' => UserRole::BANK_REVIEWER->value,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
        ]);
    }

    public function test_bank_reject_terminal_requires_comment(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['comment']]);
    }

    public function test_bank_reject_terminal_comment_min_three_chars(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'ab'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['comment']]);
    }

    public function test_bank_reject_terminal_sod_creator_cannot_reject_own(): void
    {
        // bankReviewer creates the request
        $request = $this->makeRequest($this->bank, $this->bankReviewer, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'تعارض مصالح'])
            ->assertForbidden();
    }

    public function test_bank_reject_terminal_wrong_status_returns_422(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'رفض نهائي'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_bank_reject_terminal_cross_bank_forbidden(): void
    {
        $otherReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->otherBank);
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($otherReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'رفض نهائي'])
            ->assertForbidden();
    }

    public function test_bank_reject_terminal_sets_rejected_by(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'رفض نهائي']);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'rejected_by' => $this->bankReviewer->id,
            'status' => RequestStatus::BANK_REJECTED->value,
        ]);
    }

    // ─── Story 8.3: Immutability of BANK_REJECTED ─────────────────────────────

    public function test_bank_rejected_blocks_further_transitions(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_bank_rejected_transition_immutability_via_service(): void
    {
        // BANK_REJECTED is terminal — any further transition must throw InvalidTransitionException
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $this->expectException(InvalidTransitionException::class);
        app(WorkflowService::class)->transition($request, 'bank_approve', $this->bankReviewer);
    }

    // ─── Story 8.3: Legacy bank-reject still works (AC5) ──────────────────────

    public function test_legacy_bank_reject_still_lands_in_draft_rejected_internal(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'مستندات ناقصة'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::DRAFT_REJECTED_INTERNAL->value);
    }
}

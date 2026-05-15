<?php

namespace Tests\Feature\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
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
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function advanceTo(ImportRequest $request, RequestStatus $status, User $actor): ImportRequest
    {
        $service = app(WorkflowService::class);
        $map = [
            RequestStatus::SUBMITTED => ['submit', $this->dataEntry],
            RequestStatus::BANK_REVIEW => ['bank_begin_review', $this->bankReviewer],
        ];

        foreach ($map as $targetStatus => [$action, $defaultActor]) {
            if ($request->status === $targetStatus) {
                break;
            }
            $request = $service->transition($request, $action, $actor->role === $defaultActor->role ? $actor : $defaultActor);
        }

        return $request->refresh();
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
        ]);
    }

    public function test_submit_from_draft_rejected_internal_works(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::DRAFT_REJECTED_INTERNAL);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::SUBMITTED->value);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'submitted_by' => $this->dataEntry->id,
            'resubmitted_by' => $this->dataEntry->id,
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

    public function test_bank_approve_sets_reviewed_by(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve");

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'reviewed_by' => $this->bankReviewer->id,
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
        ]);
    }

    public function test_post_approve_request_is_permanently_locked(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);
        $request = app(WorkflowService::class)->transition($request, 'bank_approve', $this->bankReviewer);

        // Auto-chains to SUPPORT_REVIEW_PENDING — must be non-editable
        $this->assertFalse($request->isEditable(), 'Request must be locked after bank approval');
        $this->assertFalse($request->status->isEditable());
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
        // Reviewer IS the creator
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
            ->assertOk();

        $items = $response->json('data.data') ?? $response->json('data');
        $bankIds = collect($items)->pluck('bank_id')->unique()->values()->all();
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

    public function test_submit_wrong_status_returns_422(): void
    {
        // Already submitted — can't submit again
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertStatus(422);
    }

    public function test_bank_approve_wrong_status_returns_422(): void
    {
        // Request still SUBMITTED — bank_approve expects BANK_REVIEW
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-approve")
            ->assertStatus(422);
    }
}

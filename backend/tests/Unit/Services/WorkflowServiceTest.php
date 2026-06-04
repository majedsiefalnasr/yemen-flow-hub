<?php

namespace Tests\Unit\Services;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\DirectStatusMutationException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SelfReviewException;
use App\Exceptions\UnauthorizedTransitionException;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Workflow\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $service;

    private Bank $bank;

    private Bank $otherBank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowService::class);
        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
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
            'email' => "user{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── AC-6: WorkflowService::transition() behaviors ─────────────────────────

    public function test_valid_transition_changes_status_via_ioc_binding(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $dataEntry, RequestStatus::DRAFT);

        $result = $this->service->transition($request, 'submit', $dataEntry);

        $this->assertSame(RequestStatus::SUBMITTED, $result->status);
    }

    public function test_transition_creates_stage_history_record(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $dataEntry, RequestStatus::DRAFT);

        $this->service->transition($request, 'submit', $dataEntry);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::DRAFT->value,
            'to_status' => RequestStatus::SUBMITTED->value,
            'action' => 'submit',
            'actor_id' => $dataEntry->id,
            'actor_role' => UserRole::DATA_ENTRY->value,
        ]);
    }

    public function test_transition_creates_audit_log_record(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $dataEntry, RequestStatus::DRAFT);

        $this->service->transition($request, 'submit', $dataEntry);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $dataEntry->id,
            'user_role' => UserRole::DATA_ENTRY->value,
        ]);
    }

    public function test_unknown_action_throws_invalid_transition_exception(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $dataEntry, RequestStatus::DRAFT);

        $this->expectException(InvalidTransitionException::class);
        $this->service->transition($request, 'nonexistent_action', $dataEntry);
    }

    public function test_wrong_from_status_throws_invalid_transition_exception(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        // Request is SUBMITTED but 'submit' expects DRAFT or DRAFT_REJECTED_INTERNAL
        $request = $this->makeRequest($this->bank, $dataEntry, RequestStatus::SUBMITTED);

        $this->expectException(InvalidTransitionException::class);
        $this->service->transition($request, 'submit', $dataEntry);
    }

    public function test_wrong_role_throws_unauthorized_transition_exception(): void
    {
        $bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        // 'submit' is only allowed for DATA_ENTRY
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::DRAFT);

        $this->expectException(UnauthorizedTransitionException::class);
        $this->service->transition($request, 'submit', $bankReviewer);
    }

    public function test_cross_bank_actor_throws_unauthorized_transition_exception(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $otherDataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::DRAFT);

        $this->expectException(UnauthorizedTransitionException::class);
        $this->service->transition($request, 'submit', $otherDataEntry);
    }

    public function test_self_review_throws_self_review_exception_on_bank_approve(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);

        // Put request in BANK_REVIEW with the reviewer as creator (simulate self-review attempt)
        app()->instance('workflow.transition.active', true);
        try {
            $request = ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $reviewer->id,  // reviewer IS the creator
                'currency' => 'USD',
                'amount' => 5000.00,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => RequestStatus::BANK_REVIEW,
                'current_owner_role' => UserRole::BANK_REVIEWER,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->expectException(SelfReviewException::class);
        $this->service->transition($request, 'bank_approve', $reviewer);
    }

    public function test_direct_status_mutation_throws_exception_without_ioc_binding(): void
    {
        $request = new ImportRequest;

        $this->expectException(DirectStatusMutationException::class);
        $request->setAttribute('status', RequestStatus::SUBMITTED);
    }

    public function test_ioc_binding_is_released_after_transition(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $dataEntry, RequestStatus::DRAFT);

        $this->service->transition($request, 'submit', $dataEntry);

        // After transition, the IoC binding must be released
        $this->assertFalse(app()->bound('workflow.transition.active'));
    }

    public function test_bank_approve_auto_chains_to_support_review_pending(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);

        app()->instance('workflow.transition.active', true);
        try {
            $request = ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 5000.00,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => RequestStatus::BANK_REVIEW,
                'current_owner_role' => UserRole::BANK_REVIEWER,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $result = $this->service->transition($request, 'bank_approve', $reviewer);

        // Auto-chained: BANK_APPROVED → SUPPORT_REVIEW_PENDING
        $this->assertSame(RequestStatus::SUPPORT_REVIEW_PENDING, $result->status);
    }
}

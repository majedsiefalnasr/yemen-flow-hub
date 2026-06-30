<?php

namespace Tests\Feature\Workflow;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BankRejectV2EraGateTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $dataEntry;

    private User $bankReviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = Bank::query()->create(['name' => 'اليمني', 'code' => 'YCB', 'is_active' => true]);
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY);
        $this->bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER);
    }

    private function makeUser(UserRole $role): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@eragate.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $this->bank->id ?? null,
            'is_active' => true,
        ]);
    }

    private function makeRequest(int $votingRuleVersion): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $this->dataEntry->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'status' => RequestStatus::BANK_REVIEW,
                'current_owner_role' => UserRole::BANK_REVIEWER,
                'voting_rule_version' => $votingRuleVersion,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── AC3: v2 reviewer reject attempts denied ──────────────────────────────

    public function test_v2_bank_reject_is_denied(): void
    {
        $request = $this->makeRequest(2);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'سبب الرفض'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'status' => RequestStatus::BANK_REVIEW->value,
        ]);
    }

    public function test_v2_bank_reject_terminal_is_denied(): void
    {
        $request = $this->makeRequest(2);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'رفض نهائي'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'status' => RequestStatus::BANK_REVIEW->value,
        ]);
    }

    public function test_v2_reject_denial_writes_authorization_failure_audit(): void
    {
        $request = $this->makeRequest(2);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'سبب الرفض'])
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankReviewer->id,
            'action' => AuditAction::AUTHORIZATION_FAILURE->value,
        ]);
    }

    // ─── AC1/AC4: v1 reviewer reject permitted (unchanged, in-flight at cutover) ─

    public function test_v1_bank_reject_is_permitted(): void
    {
        $request = $this->makeRequest(1);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject", ['reason' => 'سبب الرفض'])
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::DRAFT_REJECTED_INTERNAL->value);
    }

    public function test_v1_bank_reject_terminal_is_permitted(): void
    {
        $request = $this->makeRequest(1);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-reject-terminal", ['comment' => 'رفض نهائي'])
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::BANK_REJECTED->value);
    }

    // ─── AC2: return_to_data_entry (bank_return_to_intake) available for both eras ─

    public function test_bank_return_available_for_v2(): void
    {
        $request = $this->makeRequest(2);

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى التصحيح'])
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::BANK_RETURNED->value);
    }
}

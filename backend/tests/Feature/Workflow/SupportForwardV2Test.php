<?php

namespace Tests\Feature\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupportForwardV2Test extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $dataEntry;

    private User $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = Bank::query()->create(['name' => 'اليمني', 'code' => 'YCB', 'is_active' => true]);
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->support = $this->makeUser(UserRole::SUPPORT_COMMITTEE, null);
    }

    private function makeUser(UserRole $role, ?Bank $bank): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@supportfwd.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeClaimedRequest(int $votingRuleVersion): ImportRequest
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
                'status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
                'current_owner_role' => UserRole::SUPPORT_COMMITTEE,
                'claimed_by' => $this->support->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(15),
                'voting_rule_version' => $votingRuleVersion,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── AC2/AC3: v2 forward happy path ───────────────────────────────────────

    public function test_v2_forward_auto_chains_to_waiting_for_swift(): void
    {
        $request = $this->makeClaimedRequest(2);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-forward-to-executive", ['comment' => 'إحالة للجنة التنفيذية'])
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::WAITING_FOR_SWIFT->value);
    }

    public function test_v2_forward_records_comment_in_stage_history_and_audit(): void
    {
        $request = $this->makeClaimedRequest(2);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-forward-to-executive", ['comment' => 'إحالة للجنة التنفيذية']);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'action' => 'support_forward_to_executive',
            'actor_id' => $this->support->id,
            'reason' => 'إحالة للجنة التنفيذية',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->support->id,
            'user_role' => UserRole::SUPPORT_COMMITTEE->value,
            'subject_id' => $request->id,
        ]);
    }

    // ─── AC3: comment mandatory ───────────────────────────────────────────────

    public function test_v2_forward_requires_comment(): void
    {
        $request = $this->makeClaimedRequest(2);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-forward-to-executive", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.comment.0', 'comment.required');
    }

    public function test_v2_forward_requires_comment_min_3_chars(): void
    {
        $request = $this->makeClaimedRequest(2);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-forward-to-executive", ['comment' => 'ab'])
            ->assertStatus(422);
    }

    // ─── AC1: v2 approve/reject denied ────────────────────────────────────────

    public function test_v2_support_approve_is_denied(): void
    {
        $request = $this->makeClaimedRequest(2);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-approve")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_v2_support_reject_is_denied(): void
    {
        $request = $this->makeClaimedRequest(2);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-reject", ['reason' => 'سبب الرفض'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    // ─── AC4: v1 approve permitted (in-flight at cutover); v1 forward unavailable ─

    public function test_v1_support_approve_is_permitted(): void
    {
        $request = $this->makeClaimedRequest(1);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-approve")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::WAITING_FOR_SWIFT->value);
    }

    public function test_v1_forward_is_unavailable(): void
    {
        $request = $this->makeClaimedRequest(1);

        $this->actingAs($this->support)
            ->postJson("/api/workflow/{$request->id}/support-forward-to-executive", ['comment' => 'إحالة'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }
}

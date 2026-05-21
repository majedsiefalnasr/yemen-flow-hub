<?php

namespace Tests\Feature\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\RequestReturnedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BankReturnTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Bank $otherBank;
    private User $dataEntry;
    private User $bankReviewer;
    private User $otherBankReviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = Bank::query()->create(['name' => 'اليمني', 'code' => 'YCB', 'is_active' => true]);
        $this->otherBank = Bank::query()->create(['name' => 'آخر', 'code' => 'OTH', 'is_active' => true]);
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->otherBankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->otherBank);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;
        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@brtest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(RequestStatus $status = RequestStatus::BANK_REVIEW): ImportRequest
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
                'goods_type' => 'مواد غذائية',
                'payment_terms' => 'LC',
                'invoice_number' => 'INV-BR-001',
                'invoice_date' => now()->subDays(2)->toDateString(),
                'origin_country' => 'اليمن',
                'arrival_port' => 'ميناء عدن',
                'customs_office' => 'جمارك عدن',
                'status' => $status,
                'current_owner_role' => UserRole::BANK_REVIEWER,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── AC3: Happy path ─────────────────────────────────────────────────────

    public function test_bank_reviewer_can_return_bank_review_request_to_intake(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::BANK_RETURNED->value);
    }

    public function test_bank_return_records_stage_history_with_comment(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات']);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::BANK_REVIEW->value,
            'to_status' => RequestStatus::BANK_RETURNED->value,
            'action' => 'bank_return_to_intake',
            'actor_id' => $this->bankReviewer->id,
            'reason' => 'يرجى تصحيح المستندات',
        ]);
    }

    public function test_bank_return_records_audit_log(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankReviewer->id,
            'user_role' => UserRole::BANK_REVIEWER->value,
            'subject_id' => $request->id,
        ]);
    }

    public function test_bank_return_sets_next_owner_to_data_entry(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات']);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'current_owner_role' => UserRole::DATA_ENTRY->value,
        ]);
    }

    // ─── AC4: Comment is mandatory ───────────────────────────────────────────

    public function test_bank_return_requires_comment(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.comment.0', 'comment.required');
    }

    public function test_bank_return_requires_comment_min_3_chars(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'ab'])
            ->assertStatus(422);
    }

    // ─── AC5: Authorization ──────────────────────────────────────────────────

    public function test_data_entry_cannot_call_bank_return(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertStatus(403);
    }

    public function test_bank_reviewer_from_different_bank_cannot_bank_return(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->otherBankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertStatus(403);
    }

    // ─── AC6: submit from BANK_RETURNED increments revision_count ───────────

    public function test_submit_from_bank_returned_transitions_to_submitted_and_increments_revision(): void
    {
        $request = $this->makeRequest(RequestStatus::BANK_RETURNED);

        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['current_owner_role' => UserRole::DATA_ENTRY, 'revision_count' => 1])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::SUBMITTED->value);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'revision_count' => 2,
        ]);
    }

    // ─── AC7: Notification dispatch ─────────────────────────────────────────

    public function test_bank_return_dispatches_notification_to_data_entry_users(): void
    {
        Notification::fake();

        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertOk();

        Notification::assertSentTo($this->dataEntry, RequestReturnedNotification::class);
    }

    public function test_bank_return_notification_payload_contains_required_keys(): void
    {
        Notification::fake();

        $request = $this->makeRequest();

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return", ['comment' => 'يرجى تصحيح المستندات']);

        Notification::assertSentTo(
            $this->dataEntry,
            RequestReturnedNotification::class,
            function (RequestReturnedNotification $notification) use ($request): bool {
                $payload = $notification->toArray(new \stdClass());
                return $payload['type'] === 'request_returned'
                    && $payload['from_role'] === UserRole::BANK_REVIEWER->value
                    && $payload['comment'] === 'يرجى تصحيح المستندات'
                    && $payload['request_id'] === $request->id
                    && $payload['reference_number'] === $request->reference_number;
            }
        );
    }
}

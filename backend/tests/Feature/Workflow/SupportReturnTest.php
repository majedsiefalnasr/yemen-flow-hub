<?php

namespace Tests\Feature\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\RequestReturnedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupportReturnTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private User $dataEntry;
    private User $supportMember;
    private User $otherSupportMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = Bank::query()->create(['name' => 'اليمني', 'code' => 'YCB', 'is_active' => true]);
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->supportMember = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->otherSupportMember = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;
        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@srtest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeClaimedRequest(): ImportRequest
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
                'invoice_number' => 'INV-SR-001',
                'invoice_date' => now()->subDays(2)->toDateString(),
                'origin_country' => 'اليمن',
                'arrival_port' => 'ميناء عدن',
                'customs_office' => 'جمارك عدن',
                'status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
                'current_owner_role' => UserRole::SUPPORT_COMMITTEE,
                'claimed_by' => $this->supportMember->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(15),
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── AC1/AC2: Enum & transition map ─────────────────────────────────────

    public function test_support_returned_enum_is_not_terminal(): void
    {
        $this->assertFalse(RequestStatus::SUPPORT_RETURNED->isTerminal());
    }

    public function test_support_returned_enum_is_editable(): void
    {
        $this->assertTrue(RequestStatus::SUPPORT_RETURNED->isEditable());
    }

    // ─── AC3: Happy path ─────────────────────────────────────────────────────

    public function test_support_member_can_return_in_progress_request_to_intake(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::SUPPORT_RETURNED->value)
            ->assertJsonPath('data.current_owner_role', UserRole::DATA_ENTRY->value);
    }

    public function test_support_return_records_stage_history_with_comment(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات']);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value,
            'to_status' => RequestStatus::SUPPORT_RETURNED->value,
            'action' => 'support_return_to_intake',
            'actor_id' => $this->supportMember->id,
            'reason' => 'يرجى تصحيح المستندات',
        ]);
    }

    public function test_support_return_records_audit_log(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->supportMember->id,
            'user_role' => UserRole::SUPPORT_COMMITTEE->value,
            'subject_id' => $request->id,
        ]);
    }

    // ─── AC3: Claim released on transition ──────────────────────────────────

    public function test_support_return_releases_claim_db_fields(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات']);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'claimed_by' => null,
            'claimed_at' => null,
            'claim_expires_at' => null,
        ]);
    }

    public function test_support_return_releases_redis_claim_key(): void
    {
        $request = $this->makeClaimedRequest();
        $cacheKey = "support_claim:{$request->id}";
        Cache::put($cacheKey, $this->supportMember->id, now()->addMinutes(15));

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات']);

        $this->assertNull(Cache::get($cacheKey));
    }

    // ─── AC4: Mandatory comment ──────────────────────────────────────────────

    public function test_support_return_requires_comment(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.comment.0', 'comment.required');
    }

    public function test_support_return_requires_comment_min_3_chars(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'ab'])
            ->assertStatus(422);
    }

    // ─── AC4: Claim guard ────────────────────────────────────────────────────

    public function test_support_member_without_claim_gets_403_claim_not_held(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->otherSupportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertStatus(403);
    }

    // ─── AC4: Role guard ─────────────────────────────────────────────────────

    public function test_data_entry_cannot_call_support_return(): void
    {
        $request = $this->makeClaimedRequest();

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertStatus(403);
    }

    // ─── AC5: submit from SUPPORT_RETURNED re-enters bank queue ─────────────

    public function test_submit_from_support_returned_transitions_to_submitted(): void
    {
        app()->instance('workflow.transition.active', true);
        try {
            $request = ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $this->dataEntry->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'goods_type' => 'مواد غذائية',
                'payment_terms' => 'LC',
                'invoice_number' => 'INV-SR-SUB-001',
                'invoice_date' => now()->subDays(2)->toDateString(),
                'origin_country' => 'اليمن',
                'arrival_port' => 'ميناء عدن',
                'customs_office' => 'جمارك عدن',
                'status' => RequestStatus::SUPPORT_RETURNED,
                'current_owner_role' => UserRole::DATA_ENTRY,
                'revision_count' => 1,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->actingAs($this->dataEntry)
            ->postJson("/api/workflow/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', RequestStatus::SUBMITTED->value)
            ->assertJsonPath('data.current_owner_role', UserRole::BANK_REVIEWER->value);

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'revision_count' => 2,
        ]);
    }

    // ─── AC6: Notification ───────────────────────────────────────────────────

    public function test_support_return_dispatches_notification_to_data_entry_users(): void
    {
        Notification::fake();

        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات'])
            ->assertOk();

        Notification::assertSentTo($this->dataEntry, RequestReturnedNotification::class);
    }

    public function test_support_return_notification_payload_contains_from_role_and_comment(): void
    {
        Notification::fake();

        $request = $this->makeClaimedRequest();

        $this->actingAs($this->supportMember)
            ->postJson("/api/workflow/{$request->id}/support-return", ['comment' => 'يرجى تصحيح المستندات']);

        Notification::assertSentTo(
            $this->dataEntry,
            RequestReturnedNotification::class,
            function (RequestReturnedNotification $notification) use ($request): bool {
                $payload = $notification->toArray(new \stdClass());
                return $payload['type'] === 'request_returned'
                    && $payload['from_role'] === UserRole::SUPPORT_COMMITTEE->value
                    && $payload['comment'] === 'يرجى تصحيح المستندات'
                    && $payload['request_id'] === $request->id
                    && $payload['reference_number'] === $request->reference_number;
            }
        );
    }

    // ─── Unit: enum label ────────────────────────────────────────────────────

    public function test_support_returned_enum_label(): void
    {
        $this->assertStringContainsString('إعادة من المساندة', RequestStatus::SUPPORT_RETURNED->label());
    }
}

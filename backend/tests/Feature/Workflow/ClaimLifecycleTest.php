<?php

namespace Tests\Feature\Workflow;

use App\Console\Commands\ExpireClaimsCommand;
use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Notifications\ClaimReleasedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClaimLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private User $supportUser;
    private User $supportUser2;
    private User $bankReviewer;
    private User $cbyadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = $this->makeBank('YCB');
        $this->supportUser = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->supportUser2 = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->cbyadmin = $this->makeUser(UserRole::CBY_ADMIN);
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
            'email' => "user{$counter}@claimtest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(RequestStatus $status = RequestStatus::SUPPORT_REVIEW_PENDING): ImportRequest
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $dataEntry->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Equipment',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::SUPPORT_COMMITTEE,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function claimKey(ImportRequest $request): string
    {
        return "support_claim:{$request->id}";
    }

    // ─── AC-1: Claim succeeds → status, claimed_by, Redis key ─────────────────

    public function test_support_user_can_claim_pending_request(): void
    {
        $request = $this->makeRequest();

        $response = $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review");

        $response->assertOk();
        $request->refresh();
        $this->assertEquals(RequestStatus::SUPPORT_REVIEW_IN_PROGRESS, $request->status);
        $this->assertEquals($this->supportUser->id, $request->claimed_by);
        $this->assertNotNull($request->claimed_at);
        $this->assertNotNull($request->claim_expires_at);
        $this->assertTrue(Cache::has($this->claimKey($request)));
    }

    public function test_claim_creates_stage_history_and_audit(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::SUPPORT_REVIEW_PENDING->value,
            'to_status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value,
            'actor_id' => $this->supportUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $request->id,
            'user_id' => $this->supportUser->id,
        ]);
    }

    // ─── AC-2: 409 on double-claim ─────────────────────────────────────────────

    public function test_second_claim_attempt_returns_409(): void
    {
        $request = $this->makeRequest();

        // User A claims
        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        // User B tries to claim
        $response = $this->actingAs($this->supportUser2)
            ->postJson("/api/workflow/{$request->id}/claim-support-review");

        $response->assertStatus(409);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_409_identifies_current_claim_holder(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $response = $this->actingAs($this->supportUser2)
            ->postJson("/api/workflow/{$request->id}/claim-support-review");

        $response->assertStatus(409);
        // Response should include info about who holds the claim
        $this->assertStringContainsString(
            $this->supportUser->name,
            $response->json('message') ?? ''
        );
    }

    // ─── AC-3: Auto-release command ────────────────────────────────────────────

    public function test_expire_claims_command_releases_expired_claim(): void
    {
        $request = $this->makeRequest();

        // Claim it
        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        // Force expire: set claim_expires_at to past
        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['claim_expires_at' => now()->subMinute()])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        Artisan::call(ExpireClaimsCommand::class);

        $request->refresh();
        $this->assertEquals(RequestStatus::SUPPORT_REVIEW_PENDING, $request->status);
        $this->assertNull($request->claimed_by);
        $this->assertNull($request->claimed_at);
    }

    public function test_expire_claims_creates_history_for_auto_release(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['claim_expires_at' => now()->subMinute()])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        Artisan::call(ExpireClaimsCommand::class);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'from_status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value,
            'to_status' => RequestStatus::SUPPORT_REVIEW_PENDING->value,
            'action' => 'support_release',
        ]);
    }

    public function test_expire_claims_ignores_active_claims(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        // Do NOT expire — claim_expires_at is in the future
        Artisan::call(ExpireClaimsCommand::class);

        $request->refresh();
        $this->assertEquals(RequestStatus::SUPPORT_REVIEW_IN_PROGRESS, $request->status);
        $this->assertEquals($this->supportUser->id, $request->claimed_by);
    }

    // ─── AC-4: Heartbeat refreshes TTL ─────────────────────────────────────────

    public function test_heartbeat_refreshes_claim_ttl(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $before = $request->refresh()->claim_expires_at;

        // Small sleep to ensure timestamp differs
        sleep(1);

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review/heartbeat")
            ->assertOk();

        $after = $request->refresh()->claim_expires_at;
        $this->assertTrue($after->greaterThan($before));
        $this->assertTrue(Cache::has($this->claimKey($request)));
    }

    public function test_heartbeat_returns_403_for_non_holder(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser2)
            ->postJson("/api/workflow/{$request->id}/claim-support-review/heartbeat")
            ->assertForbidden();
    }

    // ─── AC-5: Manual release ──────────────────────────────────────────────────

    public function test_claim_holder_can_release(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->deleteJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $request->refresh();
        $this->assertEquals(RequestStatus::SUPPORT_REVIEW_PENDING, $request->status);
        $this->assertNull($request->claimed_by);
        $this->assertFalse(Cache::has($this->claimKey($request)));
    }

    public function test_non_holder_cannot_release(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser2)
            ->deleteJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertForbidden();
    }

    // ─── AC-6: Support approve ─────────────────────────────────────────────────

    public function test_claim_holder_can_approve(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/support-approve")
            ->assertOk()
            ->assertJsonPath('data.support_reviewed_by', $this->supportUser->id)
            ->assertJsonPath('data.support_reviewed_by_user.id', $this->supportUser->id)
            ->assertJsonPath('data.support_reviewed_by_user.name', $this->supportUser->name);

        $request->refresh();
        $this->assertEquals(RequestStatus::WAITING_FOR_SWIFT, $request->status);
        $this->assertEquals($this->supportUser->id, $request->support_reviewed_by);
        $this->assertNull($request->claimed_by);
    }

    public function test_non_holder_cannot_approve(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser2)
            ->postJson("/api/workflow/{$request->id}/support-approve")
            ->assertForbidden();
    }

    // ─── AC-7: Support reject ──────────────────────────────────────────────────

    public function test_claim_holder_can_reject_with_reason(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/support-reject", ['reason' => 'مستندات ناقصة'])
            ->assertOk();

        $request->refresh();
        $this->assertEquals(RequestStatus::SUPPORT_REJECTED, $request->status);
        $this->assertEquals(UserRole::BANK_REVIEWER, $request->current_owner_role);
        $this->assertNull($request->claimed_by);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'to_status' => RequestStatus::SUPPORT_REJECTED->value,
            'reason' => 'مستندات ناقصة',
        ]);
    }

    // ─── AC-8: bank-return-after-support-reject ────────────────────────────────

    public function test_bank_reviewer_can_return_after_support_reject(): void
    {
        $request = $this->makeRequest(RequestStatus::SUPPORT_REJECTED);
        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['current_owner_role' => UserRole::BANK_REVIEWER])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-return-after-support-reject")
            ->assertOk();

        $request->refresh();
        $this->assertEquals(RequestStatus::DRAFT_REJECTED_INTERNAL, $request->status);
        $this->assertEquals(UserRole::DATA_ENTRY, $request->current_owner_role);
    }

    public function test_support_committee_cannot_use_bank_return_route(): void
    {
        $request = $this->makeRequest(RequestStatus::SUPPORT_REJECTED);

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/bank-return-after-support-reject")
            ->assertForbidden();
    }

    // ─── AC-9: bank-finalize-rejection ─────────────────────────────────────────

    public function test_bank_reviewer_can_finalize_rejection(): void
    {
        $request = $this->makeRequest(RequestStatus::SUPPORT_REJECTED);
        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['current_owner_role' => UserRole::BANK_REVIEWER])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->actingAs($this->bankReviewer)
            ->postJson("/api/workflow/{$request->id}/bank-finalize-rejection")
            ->assertOk();

        $request->refresh();
        $this->assertEquals(RequestStatus::SUPPORT_REJECTED, $request->status);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'action' => 'bank_finalize_rejection',
        ]);
    }

    // ─── Auth guard ────────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_claim(): void
    {
        $request = $this->makeRequest();

        $this->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertUnauthorized();
    }

    // ─── AC3: Manual release dispatch + audit ──────────────────────────────────

    public function test_manual_release_dispatches_notification_to_cby_admins(): void
    {
        Notification::fake();

        $cbyadmin2 = $this->makeUser(UserRole::CBY_ADMIN);
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->deleteJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        Notification::assertSentTo($this->cbyadmin, ClaimReleasedNotification::class);
        Notification::assertSentTo($cbyadmin2, ClaimReleasedNotification::class);
        Notification::assertNotSentTo($this->supportUser, ClaimReleasedNotification::class);
    }

    public function test_manual_release_notification_has_correct_payload(): void
    {
        Notification::fake();

        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->deleteJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        Notification::assertSentTo(
            $this->cbyadmin,
            ClaimReleasedNotification::class,
            function (ClaimReleasedNotification $notification) use ($request) {
                $payload = $notification->toArray(new \stdClass());
                return $payload['type'] === 'claim_released'
                    && $payload['reason'] === 'manual'
                    && $payload['request_id'] === $request->id
                    && $payload['released_by_user_id'] === $this->supportUser->id;
            }
        );
    }

    public function test_manual_release_by_cby_admin_preserves_releaser_identity(): void
    {
        Notification::fake();

        $recipient = $this->makeUser(UserRole::CBY_ADMIN);
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->cbyadmin)
            ->deleteJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        Notification::assertSentTo(
            $recipient,
            ClaimReleasedNotification::class,
            function (ClaimReleasedNotification $notification) {
                $payload = $notification->toArray(new \stdClass());
                return $payload['reason'] === 'manual'
                    && $payload['released_by_user_id'] === $this->cbyadmin->id
                    && $payload['released_by_name'] === $this->cbyadmin->name;
            }
        );
    }

    public function test_manual_release_writes_audit_log(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->deleteJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->supportUser->id,
            'action' => AuditAction::CLAIM_RELEASED->value,
            'subject_id' => $request->id,
        ]);
    }

    // ─── AC4: TTL expiry dispatch + audit ──────────────────────────────────────

    public function test_ttl_expiry_dispatches_notification_to_cby_admins(): void
    {
        Notification::fake();

        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['claim_expires_at' => now()->subMinute()])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        Artisan::call(ExpireClaimsCommand::class);

        Notification::assertSentTo($this->cbyadmin, ClaimReleasedNotification::class);
    }

    public function test_ttl_expiry_notification_has_ttl_reason_and_null_user(): void
    {
        Notification::fake();

        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['claim_expires_at' => now()->subMinute()])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        Artisan::call(ExpireClaimsCommand::class);

        Notification::assertSentTo(
            $this->cbyadmin,
            ClaimReleasedNotification::class,
            function (ClaimReleasedNotification $notification) {
                $payload = $notification->toArray(new \stdClass());
                return $payload['reason'] === 'ttl_expired'
                    && $payload['released_by_user_id'] === null;
            }
        );
    }

    public function test_ttl_expiry_writes_audit_log_with_null_user(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill(['claim_expires_at' => now()->subMinute()])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        Artisan::call(ExpireClaimsCommand::class);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'action' => AuditAction::CLAIM_RELEASED->value,
            'subject_id' => $request->id,
        ]);
    }

    // ─── AC5: Preference enforcement ───────────────────────────────────────────

    public function test_cby_admin_with_claim_released_pref_false_does_not_get_notification(): void
    {
        Notification::fake();

        app()->instance('workflow.transition.active', true);
        try {
            $this->cbyadmin->forceFill([
                'user_preferences' => ['notification_preferences' => ['claim_released' => false]],
            ])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $request = $this->makeRequest();

        $this->actingAs($this->supportUser)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->deleteJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertOk();

        Notification::assertNotSentTo($this->cbyadmin, ClaimReleasedNotification::class);
    }
}

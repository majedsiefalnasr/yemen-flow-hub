<?php

namespace Tests\Feature\Settings;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Mail\TestEmailMail;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class AdminSettingsControllerTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    private function makeCbyAdmin(string $email = 'admin@cby.gov.ye'): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'CBY Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN,
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    // --- GET /api/admin/settings ---

    public function test_get_admin_settings_returns_all_settings_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/settings');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.support_claim_ttl', 15);
        $response->assertJsonPath('data.voting_session_timeout', 60);
        $response->assertJsonPath('data.pdf_upload_size_limit', 10);
        $response->assertJsonPath('data.login_lockout_duration', 15);
        $response->assertJsonPath('data.notifications_phase_1_enabled', false);
    }

    public function test_get_admin_settings_forbidden_for_non_admin_user(): void
    {
        $user = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'entry@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/admin/settings');

        $response->assertStatus(403);
    }

    public function test_get_admin_settings_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/admin/settings')->assertStatus(401);
    }

    // --- PUT /api/admin/settings/{key} ---

    public function test_update_support_claim_ttl_with_valid_value(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/support_claim_ttl', [
            'value' => 20,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.value', 20);

        $setting = SystemSetting::where('key', 'support_claim_ttl')->first();
        $this->assertEquals(20, $setting->value);
        $this->assertEquals($admin->id, $setting->updated_by);
    }

    public function test_update_voting_session_timeout_with_valid_value(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/voting_session_timeout', [
            'value' => 90,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.value', 90);
    }

    public function test_update_pdf_upload_size_limit_with_valid_value(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/pdf_upload_size_limit', [
            'value' => 25,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.value', 25);
    }

    public function test_update_boolean_feature_toggle(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/notifications_phase_1_enabled', [
            'value' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.value', true);
    }

    public function test_update_setting_fails_with_value_below_minimum(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/support_claim_ttl', [
            'value' => 2,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_update_setting_fails_with_value_above_maximum(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/support_claim_ttl', [
            'value' => 100,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_update_setting_fails_with_invalid_key(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->putJson('/api/admin/settings/invalid_key', [
            'value' => 10,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_update_setting_fails_for_non_admin_user(): void
    {
        $user = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'entry@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/admin/settings/support_claim_ttl', [
            'value' => 20,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_setting_logs_audit_entry(): void
    {
        $admin = $this->makeCbyAdmin();

        $this->actingAs($admin)->putJson('/api/admin/settings/support_claim_ttl', [
            'value' => 25,
        ]);

        $auditLog = AuditLog::where('action', AuditAction::SETTINGS_UPDATED->value)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertNotNull($auditLog);
    }

    public function test_update_setting_returns_401_when_unauthenticated(): void
    {
        $this->putJson('/api/admin/settings/support_claim_ttl', [
            'value' => 20,
        ])->assertStatus(401);
    }

    // --- POST /api/admin/settings/{key}/reset ---

    public function test_reset_setting_to_default(): void
    {
        $admin = $this->makeCbyAdmin();

        // First update the setting
        $this->actingAs($admin)->putJson('/api/admin/settings/support_claim_ttl', [
            'value' => 30,
        ]);

        // Then reset it
        $response = $this->actingAs($admin)->postJson('/api/admin/settings/support_claim_ttl/reset');

        $response->assertStatus(200);
        $response->assertJsonPath('data.value', 15);

        $setting = SystemSetting::where('key', 'support_claim_ttl')->first();
        $this->assertEquals(15, $setting->value);
    }

    public function test_reset_setting_logs_audit_entry(): void
    {
        $admin = $this->makeCbyAdmin();

        $this->actingAs($admin)->postJson('/api/admin/settings/support_claim_ttl/reset');

        $auditLog = AuditLog::where('action', AuditAction::SETTINGS_UPDATED->value)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertNotNull($auditLog);
    }

    public function test_reset_setting_fails_with_invalid_key(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->postJson('/api/admin/settings/invalid_key/reset');

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_reset_setting_fails_for_non_admin_user(): void
    {
        $user = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'entry@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/admin/settings/support_claim_ttl/reset');

        $response->assertStatus(403);
    }

    public function test_reset_setting_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/admin/settings/support_claim_ttl/reset')->assertStatus(401);
    }

    // --- POST /api/admin/settings/email/test ---

    public function test_test_email_sends_to_admin_own_email_by_default(): void
    {
        Mail::fake();
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->postJson('/api/admin/settings/email/test');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.sent', true);
        $response->assertJsonPath('data.recipient', $admin->email);
        Mail::assertSent(TestEmailMail::class);
    }

    public function test_test_email_sends_to_provided_test_address(): void
    {
        Mail::fake();
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->postJson('/api/admin/settings/email/test', [
            'test_address' => 'custom@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.recipient', 'custom@example.com');
        Mail::assertSent(TestEmailMail::class, fn ($mail) => $mail->hasTo('custom@example.com'));
    }

    public function test_test_email_creates_audit_log_on_success(): void
    {
        Mail::fake();
        $admin = $this->makeCbyAdmin();

        $this->actingAs($admin)->postJson('/api/admin/settings/email/test');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => AuditAction::EMAIL_TEST_SENT->value,
        ]);

        $log = AuditLog::where('action', AuditAction::EMAIL_TEST_SENT->value)->first();
        $this->assertTrue($log->metadata['success']);
        $this->assertEquals($admin->email, $log->metadata['recipient']);
        $this->assertArrayNotHasKey('error_message', $log->metadata);
    }

    public function test_test_email_creates_audit_log_on_failure(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection refused'));

        $admin = $this->makeCbyAdmin('admin2@cby.gov.ye');

        $response = $this->actingAs($admin)->postJson('/api/admin/settings/email/test');

        $response->assertStatus(500);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error_code', 'EMAIL_TEST_FAILED');

        $log = AuditLog::where('action', AuditAction::EMAIL_TEST_SENT->value)->first();
        $this->assertNotNull($log);
        $this->assertFalse($log->metadata['success']);
        $this->assertArrayHasKey('error_message', $log->metadata);
    }

    public function test_test_email_rejects_invalid_test_address(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->postJson('/api/admin/settings/email/test', [
            'test_address' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }

    public function test_test_email_forbidden_for_non_cby_admin(): void
    {
        $user = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'entry@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/admin/settings/email/test');

        $response->assertStatus(403);
    }

    public function test_test_email_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/admin/settings/email/test')->assertStatus(401);
    }
}

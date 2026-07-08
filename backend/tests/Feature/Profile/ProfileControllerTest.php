<?php

namespace Tests\Feature\Profile;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->bank = Bank::query()->create([
            'name' => 'Profile Bank',
            'code' => 'PRF',
            'is_active' => true,
        ]);
    }

    // --- GET /api/profile ---

    public function test_get_profile_returns_authenticated_user_data(): void
    {
        $user = User::query()->create([
            'name' => 'Ahmed Ali',
            'email' => 'ahmed@bank.com',
            'password' => Hash::make('password'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', 'Ahmed Ali');
        $response->assertJsonPath('data.email', 'ahmed@bank.com');
        $response->assertJsonPath('data.role', $user->asUserRole()?->value);
        $response->assertJsonPath('data.is_active', true);
    }

    public function test_get_profile_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
    }

    // --- POST /api/profile/change-password ---

    public function test_change_password_with_valid_credentials(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('oldPassword123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'oldPassword123',
            'password' => 'newPassword456',
            'password_confirmation' => 'newPassword456',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('newPassword456', $user->password));

        // Verify audit log
        $auditLog = AuditLog::where('action', AuditAction::PASSWORD_CHANGED->value)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($auditLog);
    }

    public function test_change_password_fails_with_invalid_current_password(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('oldPassword123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'wrongPassword',
            'password' => 'newPassword456',
            'password_confirmation' => 'newPassword456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');

        // Verify password was NOT changed
        $user->refresh();
        $this->assertTrue(Hash::check('oldPassword123', $user->password));
    }

    public function test_change_password_fails_with_weak_password(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('oldPassword123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        // Password too short (less than 8 characters)
        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'oldPassword123',
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');

        // Password without mixed case
        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'oldPassword123',
            'password' => 'allowercase1',
            'password_confirmation' => 'allowercase1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');

        // Password without numbers
        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'oldPassword123',
            'password' => 'NoNumbers',
            'password_confirmation' => 'NoNumbers',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_change_password_fails_with_mismatched_confirmation(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('oldPassword123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'oldPassword123',
            'password' => 'newPassword456',
            'password_confirmation' => 'differentPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_change_password_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/profile/change-password', [
            'current_password' => 'any',
            'password' => 'any',
            'password_confirmation' => 'any',
        ])->assertStatus(401);
    }

    public function test_change_password_logs_audit_entry(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('oldPassword123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'oldPassword123',
            'password' => 'newPassword456',
            'password_confirmation' => 'newPassword456',
        ]);

        $auditLog = AuditLog::where('action', AuditAction::PASSWORD_CHANGED->value)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($user->asUserRole()?->value, $auditLog->user_role);
    }

    public function test_change_password_rejects_same_password_as_current(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('CurrentPassword123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'CurrentPassword123',
            'password' => 'CurrentPassword123',
            'password_confirmation' => 'CurrentPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
        $response->assertJsonPath('errors.password.0', 'The new password must be different from the current password.');
    }

    public function test_change_password_with_wrong_current_password(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'WrongPassword123',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');
    }

    // --- GET /api/profile — stats and recent_activity ---

    public function test_get_profile_returns_stats_and_recent_activity(): void
    {
        $user = User::query()->create([
            'name' => 'Stats User',
            'email' => 'stats@bank.com',
            'password' => Hash::make('Password123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'stats' => ['total', 'in_progress', 'completed'],
                'recent_activity',
            ],
        ]);
        $this->assertIsInt($response->json('data.stats.total'));
        $this->assertIsInt($response->json('data.stats.in_progress'));
        $this->assertIsInt($response->json('data.stats.completed'));
        $this->assertIsArray($response->json('data.recent_activity'));
    }

    // --- PUT /api/profile ---

    public function test_put_profile_updates_name_email_phone(): void
    {
        $user = User::query()->create([
            'name' => 'Old Name',
            'email' => 'old@bank.com',
            'password' => Hash::make('Password123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new@bank.com',
            'phone' => '+9671234567',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.email', 'new@bank.com');
        $response->assertJsonPath('data.phone', '+9671234567');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@bank.com', $user->email);
        $this->assertEquals('+9671234567', $user->phone);
    }

    public function test_put_profile_only_updates_own_profile(): void
    {
        // The PUT /api/profile endpoint operates only on the authenticated user —
        // there is no route to update another user's profile via this endpoint.
        // Verify unauthenticated request returns 401.
        $response = $this->putJson('/api/profile', [
            'name' => 'Attacker',
            'email' => 'attacker@evil.com',
        ]);
        $response->assertStatus(401);
    }

    public function test_totp_setup_returns_one_time_backup_codes(): void
    {
        $user = User::query()->create([
            'name' => 'MFA User',
            'email' => 'mfa-user@bank.com',
            'password' => Hash::make('Password123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        $setup = $this->actingAs($user)->postJson('/api/profile/mfa/setup')->assertOk();
        $secret = $setup->json('data.secret');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->actingAs($user)->postJson('/api/profile/mfa/setup/verify', [
            'code' => $code,
        ]);

        $response->assertOk();
        $codes = $response->json('data.recovery_codes');
        $this->assertIsArray($codes);
        $this->assertCount(10, $codes);
        foreach ($codes as $backupCode) {
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $backupCode);
        }

        $user->refresh();
        $this->assertTrue($user->totp_enabled);
        $this->assertIsArray($user->totp_recovery_codes);
        $this->assertCount(10, $user->totp_recovery_codes);
        $this->assertNotContains($codes[0], $user->totp_recovery_codes);
    }

    // --- POST /api/profile/mfa/toggle ---

    public function test_post_mfa_toggle_when_setting_not_registered(): void
    {
        $user = User::query()->create([
            'name' => 'MFA User',
            'email' => 'mfa@bank.com',
            'password' => Hash::make('Password123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'mfa_enabled' => false,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        // When mfa_required setting doesn't exist, it defaults to true (secure-first)
        $response = $this->actingAs($user)->postJson('/api/profile/mfa/toggle');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'MFA is system-enforced');

        // Verify MFA was NOT toggled
        $user->refresh();
        $this->assertFalse($user->mfa_enabled);
    }

    public function test_post_mfa_toggle_returns_403_when_system_enforced(): void
    {
        $user = User::query()->create([
            'name' => 'MFA User',
            'email' => 'mfa@bank.com',
            'password' => Hash::make('Password123'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
            'mfa_enabled' => false,
        ]);
        $user = $this->assignGovernanceIdentity($user, UserRole::DATA_ENTRY);

        // Set system-enforced MFA policy
        SystemSetting::query()->create([
            'key' => 'mfa_required',
            'value' => true,
            'updated_by' => 1,
        ]);

        $response = $this->actingAs($user)->postJson('/api/profile/mfa/toggle');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'MFA is system-enforced');

        // Verify MFA was NOT toggled
        $user->refresh();
        $this->assertFalse($user->mfa_enabled);
    }
}

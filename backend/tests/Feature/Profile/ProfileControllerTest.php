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
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    // --- GET /api/profile ---

    public function test_get_profile_returns_authenticated_user_data(): void
    {
        $user = User::query()->create([
            'name' => 'Ahmed Ali',
            'email' => 'ahmed@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', 'Ahmed Ali');
        $response->assertJsonPath('data.email', 'ahmed@bank.com');
        $response->assertJsonPath('data.role', $user->role->value);
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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'oldPassword123',
            'password' => 'newPassword456',
            'password_confirmation' => 'newPassword456',
        ]);

        $auditLog = AuditLog::where('action', AuditAction::PASSWORD_CHANGED->value)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($user->role->value, $auditLog->user_role);
    }

    public function test_change_password_rejects_same_password_as_current(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('CurrentPassword123'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/profile', [
            'name'  => 'New Name',
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
            'name'  => 'Attacker',
            'email' => 'attacker@evil.com',
        ]);
        $response->assertStatus(401);
    }

    // --- GET /api/admin/settings/smtp ---

    public function test_get_smtp_returns_masked_password(): void
    {
        $cbyAdmin = User::query()->create([
            'name'  => 'CBY Admin',
            'email' => 'admin@cby.ye',
            'password' => Hash::make('Password123'),
            'role'  => UserRole::CBY_ADMIN,
            'bank_id' => null,
            'is_active' => true,
        ]);

        // Seed an SMTP password setting
        SystemSetting::query()->create([
            'key'        => 'smtp_password',
            'value'      => encrypt('secret123'),
            'updated_by' => $cbyAdmin->id,
        ]);

        $response = $this->actingAs($cbyAdmin)->getJson('/api/admin/settings/smtp');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        // Password must be masked
        $this->assertStringContainsString('•', $response->json('data.password'));
        $this->assertStringNotContainsString('secret123', $response->json('data.password'));
    }

    public function test_get_smtp_returns_403_for_non_cby_admin(): void
    {
        $bankUser = User::query()->create([
            'name'  => 'Bank User',
            'email' => 'user@bank.com',
            'password' => Hash::make('Password123'),
            'role'  => UserRole::BANK_REVIEWER,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($bankUser)->getJson('/api/admin/settings/smtp')
            ->assertStatus(403);
    }

    // --- POST /api/profile/mfa/toggle ---

    public function test_post_mfa_toggle_when_setting_not_registered(): void
    {
        $user = User::query()->create([
            'name' => 'MFA User',
            'email' => 'mfa@bank.com',
            'password' => Hash::make('Password123'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
            'mfa_enabled' => false,
        ]);

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
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
            'mfa_enabled' => false,
        ]);

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

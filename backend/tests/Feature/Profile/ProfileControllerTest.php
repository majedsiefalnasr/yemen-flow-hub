<?php

namespace Tests\Feature\Profile;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
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

    public function test_change_password_requires_user_password_to_be_set(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => null,
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/profile/change-password', [
            'current_password' => 'anyPassword',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(403);
    }

}

<?php

namespace Tests\Feature\Settings;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    // --- GET /api/settings ---

    public function test_get_settings_returns_user_preferences_with_defaults(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => [
                'language' => 'en',
                'page_size' => 50,
            ],
        ]);

        $response = $this->actingAs($user)->getJson('/api/settings');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.language', 'en');
        $response->assertJsonPath('data.page_size', 50);
        $response->assertJsonPath('data.dashboard_view', 'normal');
        $response->assertJsonPath('data.table_density', 'normal');
    }

    public function test_get_settings_returns_defaults_when_no_preferences_stored(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/settings');

        $response->assertStatus(200);
        $response->assertJsonPath('data.language', 'ar');
        $response->assertJsonPath('data.dashboard_view', 'normal');
        $response->assertJsonPath('data.table_density', 'normal');
        $response->assertJsonPath('data.page_size', 25);
        $response->assertJsonPath('data.default_filters', []);
        $response->assertJsonPath('data.notification_preferences', []);
    }

    public function test_get_settings_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/settings')->assertStatus(401);
    }

    // --- PUT /api/settings ---

    public function test_update_settings_with_valid_preferences(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'language' => 'en',
            'dashboard_view' => 'expanded',
            'table_density' => 'compact',
            'page_size' => 100,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.language', 'en');
        $response->assertJsonPath('data.dashboard_view', 'expanded');
        $response->assertJsonPath('data.table_density', 'compact');
        $response->assertJsonPath('data.page_size', 100);

        // Verify saved to database
        $user->refresh();
        $this->assertEquals('en', $user->user_preferences['language']);
        $this->assertEquals('expanded', $user->user_preferences['dashboard_view']);
    }

    public function test_update_settings_with_partial_preferences(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'language' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.language', 'en');
        $response->assertJsonPath('data.dashboard_view', 'normal');
        $response->assertJsonPath('data.page_size', 25);
    }

    public function test_update_settings_fails_with_invalid_language(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'language' => 'fr',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('language');
    }

    public function test_update_settings_fails_with_invalid_dashboard_view(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'dashboard_view' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('dashboard_view');
    }

    public function test_update_settings_fails_with_invalid_table_density(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'table_density' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('table_density');
    }

    public function test_update_settings_fails_with_invalid_page_size(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'page_size' => 15,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('page_size');
    }

    public function test_update_settings_logs_audit_entry(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)->putJson('/api/settings', [
            'language' => 'en',
            'page_size' => 50,
        ]);

        $auditLog = AuditLog::where('action', AuditAction::SETTINGS_UPDATED->value)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($user->role->value, $auditLog->user_role);
    }

    public function test_update_settings_returns_401_when_unauthenticated(): void
    {
        $this->putJson('/api/settings', [
            'language' => 'en',
        ])->assertStatus(401);
    }

    // --- POST /api/settings/reset ---

    public function test_reset_settings_clears_preferences(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => [
                'language' => 'en',
                'page_size' => 100,
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/settings/reset');

        $response->assertStatus(200);
        $response->assertJsonPath('data.language', 'ar');
        $response->assertJsonPath('data.page_size', 25);

        $user->refresh();
        $this->assertNull($user->user_preferences);
    }

    public function test_reset_settings_logs_audit_entry(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => [
                'language' => 'en',
            ],
        ]);

        $this->actingAs($user)->postJson('/api/settings/reset');

        $auditLog = AuditLog::where('action', AuditAction::SETTINGS_UPDATED->value)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
    }

    public function test_reset_settings_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/settings/reset')->assertStatus(401);
    }
}

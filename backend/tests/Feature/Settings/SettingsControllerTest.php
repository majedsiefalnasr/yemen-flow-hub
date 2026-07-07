<?php

namespace Tests\Feature\Settings;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use AssignsGovernanceIdentity;
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
        $response->assertJsonPath('data.system.version', 'defaults-v1');
        $response->assertJsonPath('data.system.branding.brandLogoUrl', '/brand/yemen-emblem.svg');
        $response->assertJsonPath('data.system.branding.brandLogoName', 'yemen-emblem.svg');
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

    public function test_get_public_settings_returns_system_branding_without_authentication(): void
    {
        SystemSetting::query()->create([
            'key' => 'settings.general',
            'value' => [
                'platformName' => 'منصة الاختبار',
                'authority' => 'جهة الاختبار',
            ],
        ]);
        SystemSetting::query()->create([
            'key' => 'settings.branding',
            'value' => [
                'brandColor' => '#0055aa',
                'brandLogoName' => 'custom-logo.svg',
                'brandLogoDataUrl' => 'data:image/svg+xml;base64,PHN2Zy8+',
            ],
        ]);

        $response = $this->getJson('/api/settings/public');

        $response->assertStatus(200);
        $response->assertJsonPath('data.general.platformName', 'منصة الاختبار');
        $response->assertJsonPath('data.general.authority', 'جهة الاختبار');
        $response->assertJsonPath('data.branding.brandColor', '#0055aa');
        $response->assertJsonPath('data.branding.brandLogoName', 'custom-logo.svg');
    }

    public function test_get_public_settings_returns_national_committee_defaults(): void
    {
        $response = $this->getJson('/api/settings/public');

        $response->assertStatus(200);
        $response->assertJsonPath('data.general.platformName', 'اللجنة الوطنية لتنظيم وتمويل الواردات');
        $response->assertJsonPath('data.general.platformNameEn', 'The National Committee for Regulating & Financing Imports');
        $response->assertJsonPath('data.general.authority', 'اللجنة الوطنية لتنظيم وتمويل الواردات');
        $response->assertJsonPath('data.general.authorityEn', 'The National Committee for Regulating & Financing Imports');
    }

    public function test_cby_admin_save_branding_persists_system_branding(): void
    {
        $this->seedGovernance();
        $user = $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN,
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);

        $response = $this->actingAs($user)->postJson('/api/settings/save-section', [
            'section' => 'theming',
            'subsection' => 'branding',
            'data' => [
                'brandColor' => '#0055aa',
                'brandLogoName' => 'custom-logo.svg',
                'brandLogoDataUrl' => 'data:image/svg+xml;base64,PHN2Zy8+',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.key', 'settings.branding');
        $response->assertJsonPath('data.value.brandColor', '#0055aa');
        $response->assertJsonPath('data.value.brandLogoName', 'custom-logo.svg');

        $this->assertDatabaseHas('system_settings', ['key' => 'settings.branding']);
        $user->refresh();
        $this->assertNull($user->user_preferences);
    }

    public function test_non_admin_cannot_save_system_branding(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/settings/save-section', [
            'section' => 'theming',
            'subsection' => 'branding',
            'data' => [
                'brandColor' => '#0055aa',
            ],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('system_settings', ['key' => 'settings.branding']);
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

    public function test_save_theming_section_persists_user_appearance_preferences(): void
    {
        SystemSetting::query()->create([
            'key' => 'settings.theming',
            'value' => [
                'mode' => 'system',
                'font' => 'IBM Plex Sans Arabic',
                'layout' => 'full',
                'sidebarVariant' => 'sidebar',
                'sidebarCollapsible' => 'icon',
                'radius' => 'md',
                'density' => 'comfortable',
                'reducedMotion' => 'system',
            ],
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/settings/save-section', [
            'section' => 'theming',
            'data' => [
                'mode' => 'dark',
                'font' => 'Cairo',
                'layout' => 'boxed',
                'sidebarVariant' => 'floating',
                'sidebarCollapsible' => 'offcanvas',
                'radius' => 'xl',
                'density' => 'compact',
                'reducedMotion' => 'always',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.theming.mode', 'dark');
        $response->assertJsonPath('data.theming.font', 'Cairo');
        $response->assertJsonPath('data.theming.layout', 'boxed');
        $response->assertJsonPath('data.theming.sidebarVariant', 'floating');
        $response->assertJsonPath('data.theming.sidebarCollapsible', 'offcanvas');
        $response->assertJsonPath('data.theming.radius', 'xl');
        $response->assertJsonPath('data.theming.density', 'compact');
        $response->assertJsonPath('data.theming.reducedMotion', 'always');

        $user->refresh();
        $this->assertEquals('dark', $user->user_preferences['theming']['mode']);
        $this->assertEquals('Cairo', $user->user_preferences['theming']['font']);
    }

    public function test_get_settings_uses_system_theming_when_user_has_no_appearance_overrides(): void
    {
        SystemSetting::query()->create([
            'key' => 'settings.theming',
            'value' => [
                'mode' => 'dark',
                'font' => 'Tajawal',
                'layout' => 'boxed',
                'density' => 'compact',
            ],
        ]);

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
        $response->assertJsonPath('data.theming.mode', 'dark');
        $response->assertJsonPath('data.theming.font', 'Tajawal');
        $response->assertJsonPath('data.theming.layout', 'boxed');
        $response->assertJsonPath('data.theming.density', 'compact');
        $response->assertJsonPath('data.theming.sidebarVariant', 'sidebar');
    }

    public function test_save_theming_section_stores_only_user_overrides_from_system_theming(): void
    {
        SystemSetting::query()->create([
            'key' => 'settings.theming',
            'value' => [
                'mode' => 'dark',
                'font' => 'Tajawal',
                'layout' => 'boxed',
                'sidebarVariant' => 'sidebar',
                'sidebarCollapsible' => 'icon',
                'radius' => 'md',
                'density' => 'comfortable',
                'reducedMotion' => 'system',
            ],
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/settings/save-section', [
            'section' => 'theming',
            'data' => [
                'mode' => 'dark',
                'font' => 'Cairo',
                'layout' => 'boxed',
                'sidebarVariant' => 'sidebar',
                'sidebarCollapsible' => 'icon',
                'radius' => 'md',
                'density' => 'compact',
                'reducedMotion' => 'system',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.theming.mode', 'dark');
        $response->assertJsonPath('data.theming.font', 'Cairo');
        $response->assertJsonPath('data.theming.layout', 'boxed');
        $response->assertJsonPath('data.theming.density', 'compact');

        $user->refresh();
        $this->assertSame([
            'font' => 'Cairo',
            'density' => 'compact',
        ], $user->user_preferences['theming']);
    }

    public function test_save_theming_section_removes_user_override_when_values_match_system_theming(): void
    {
        SystemSetting::query()->create([
            'key' => 'settings.theming',
            'value' => [
                'mode' => 'system',
                'font' => 'IBM Plex Sans Arabic',
                'layout' => 'full',
                'sidebarVariant' => 'sidebar',
                'sidebarCollapsible' => 'icon',
                'radius' => 'md',
                'density' => 'comfortable',
                'reducedMotion' => 'system',
            ],
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => null,
            'is_active' => true,
            'user_preferences' => [
                'theming' => [
                    'density' => 'compact',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/settings/save-section', [
            'section' => 'theming',
            'data' => [
                'mode' => 'system',
                'font' => 'IBM Plex Sans Arabic',
                'layout' => 'full',
                'sidebarVariant' => 'sidebar',
                'sidebarCollapsible' => 'icon',
                'radius' => 'md',
                'density' => 'comfortable',
                'reducedMotion' => 'system',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.theming.density', 'comfortable');

        $user->refresh();
        $this->assertNull($user->user_preferences);
    }
}

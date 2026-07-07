<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Settings\LogoStorageService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class LogoStorageTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    public function test_logo_stored_as_file_not_base64(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 100, 100);
        $path = app(LogoStorageService::class)->store($file);

        $this->assertStringStartsWith('logos/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_public_settings_expose_logo_url_not_dataurl(): void
    {
        Storage::fake('public');
        SystemSetting::updateOrCreate(
            ['key' => 'settings.branding'],
            ['value' => ['brandLogoPath' => 'logos/abc.png', 'brandColor' => '#0066cc']],
        );

        $public = app(SystemSettingsService::class)->getPublicSettings();

        $this->assertStringNotContainsString('data:image', $public['branding']['brandLogoUrl'] ?? '');
        $this->assertNotEmpty($public['branding']['brandLogoUrl']);
    }

    public function test_legacy_data_url_logo_resolved_without_storage_url(): void
    {
        $legacyDataUrl = 'data:image/svg+xml;base64,PHN2Zy8+';

        SystemSetting::updateOrCreate(
            ['key' => 'settings.branding'],
            ['value' => [
                'brandColor' => '#0066cc',
                'brandLogoDataUrl' => $legacyDataUrl,
            ]],
        );

        $public = app(SystemSettingsService::class)->getPublicSettings();

        $this->assertSame($legacyDataUrl, $public['branding']['brandLogoUrl']);
    }

    public function test_save_branding_with_logo_file_persists_path_and_public_url(): void
    {
        Storage::fake('public');
        $this->seedGovernance();
        $user = $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Admin User',
            'email' => 'logo-admin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);

        $file = UploadedFile::fake()->image('brand-logo.png', 120, 120);

        $response = $this->actingAs($user)->post('/api/settings/save-section', [
            'section' => 'theming',
            'subsection' => 'branding',
            'data' => [
                'brandColor' => '#112233',
                'brandLogoName' => 'brand-logo.png',
                'brandLogoFile' => $file,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.value.brandColor', '#112233');
        $response->assertJsonMissingPath('data.value.brandLogoDataUrl');

        $setting = SystemSetting::query()->where('key', 'settings.branding')->first();
        $this->assertNotEmpty($setting?->value['brandLogoPath'] ?? null);
        $this->assertStringStartsWith('logos/', $setting->value['brandLogoPath']);
        Storage::disk('public')->assertExists($setting->value['brandLogoPath']);

        $publicResponse = $this->getJson('/api/settings/public');
        $publicResponse->assertStatus(200);
        $brandLogoUrl = $publicResponse->json('data.branding.brandLogoUrl');
        $this->assertNotEmpty($brandLogoUrl);
        $this->assertStringNotContainsString('data:image', (string) $brandLogoUrl);
        $this->assertStringNotContainsString('data:image', $publicResponse->getContent());
    }
}

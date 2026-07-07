<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\LogoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LogoStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_logo_stored_as_file_not_base64(): void
    {
        \Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 100, 100);
        $path = app(LogoStorageService::class)->store($file);

        $this->assertStringStartsWith('logos/', $path);
        \Storage::disk('public')->assertExists($path);
    }

    public function test_public_settings_expose_logo_url_not_dataurl(): void
    {
        \Storage::fake('public');
        SystemSetting::updateOrCreate(
            ['key' => 'settings.branding'],
            ['value' => ['brandLogoPath' => 'logos/abc.png', 'brandColor' => '#0066cc']],
        );

        $public = app(\App\Services\Settings\SystemSettingsService::class)->getPublicSettings();

        $this->assertStringNotContainsString('data:image', $public['branding']['brandLogoUrl'] ?? '');
        $this->assertNotEmpty($public['branding']['brandLogoUrl']);
    }
}

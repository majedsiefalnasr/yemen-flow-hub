<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSettingsSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_payload_exposes_no_sensitive_config(): void
    {
        SystemSetting::query()->create([
            'key' => 'settings.general',
            'value' => [
                'platformName' => 'منصة الاختبار',
                'support_claim_ttl' => 99,
                'login_lockout_attempts' => 8,
                'smtp_host' => 'mail.internal.example',
            ],
        ]);
        SystemSetting::query()->create([
            'key' => 'settings.branding',
            'value' => [
                'brandColor' => '#0055aa',
                'support_claim_ttl' => 99,
                'mfa_required' => true,
                'audit_retention_days' => 3650,
            ],
        ]);

        $response = $this->getJson('/api/settings/public');

        $response->assertOk();
        $payload = $response->json('data');

        $this->assertSame(['version', 'general', 'branding'], array_keys($payload));

        $forbidden = ['smtp', 'login_lockout', 'mfa', 'password', 'secret', 'token', 'claims', 'audit'];
        $json = json_encode($payload);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $json, "Public settings leaked $needle.");
        }

        $this->assertArrayNotHasKey('support_claim_ttl', $payload['general']);
        $this->assertArrayNotHasKey('support_claim_ttl', $payload['branding']);
        $this->assertStringNotContainsString('support_claim_ttl', $json);
    }

    public function test_public_payload_has_version_stamp(): void
    {
        $response = $this->getJson('/api/settings/public');

        $this->assertArrayHasKey('version', $response->json('data'));
    }
}

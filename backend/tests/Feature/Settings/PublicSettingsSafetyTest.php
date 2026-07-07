<?php

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSettingsSafetyTest extends TestCase
{
    use RefreshDatabase;
    public function test_public_payload_exposes_no_sensitive_config(): void
    {
        $response = $this->getJson('/api/settings/public');

        $response->assertOk();
        $payload = $response->json('data');

        $forbidden = ['smtp', 'login_lockout', 'mfa', 'password', 'secret', 'token', 'claims', 'audit'];
        $json = json_encode($payload);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $json, "Public settings leaked $needle.");
        }
    }

    public function test_public_payload_has_version_stamp(): void
    {
        $response = $this->getJson('/api/settings/public');

        $this->assertArrayHasKey('version', $response->json('data'));
    }
}

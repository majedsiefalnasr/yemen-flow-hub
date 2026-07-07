<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\AdminSettingsService;
use Tests\TestCase;

class PlaceboSettingsRemovedTest extends TestCase
{
    public function test_defaults_contain_only_live_keys(): void
    {
        $defaults = app(AdminSettingsService::class)->getDefaults();

        $live = [
            'support_claim_ttl', 'pdf_upload_size_limit',
            'login_lockout_attempts', 'login_lockout_duration',
            'mfa_required', 'duplicate_invoice_policy',
            'trusted_device_ttl_hours', 'step_up_window_minutes',
        ];

        $this->assertSame($live, array_keys($defaults));
    }

    public function test_placebo_keys_absent(): void
    {
        $defaults = app(AdminSettingsService::class)->getDefaults();

        $placebo = [
            'voting_session_timeout', 'support_committee_size', 'executive_committee_size',
            'minimum_quorum', 'review_timeout_hours', 'secret_voting', 'director_tiebreak',
            'notifications_phase_1_enabled', 'search_phase_1_enabled', 'customs_print_preview_enabled',
            'password_expiry_90_days', 'lockout_after_5_attempts', 'encrypt_uploads_aes256',
            'log_all_audit', 'allow_external_access',
        ];

        foreach ($placebo as $key) {
            $this->assertArrayNotHasKey($key, $defaults, "Placebo key $key must be removed.");
        }
    }
}

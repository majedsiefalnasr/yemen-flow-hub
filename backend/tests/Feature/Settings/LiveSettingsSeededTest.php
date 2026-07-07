<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\AdminSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSettingsSeededTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_live_keys_have_db_rows(): void
    {
        $this->artisan('migrate');

        $defaults = app(AdminSettingsService::class)->getDefaults();

        foreach (array_keys($defaults) as $key) {
            $this->assertNotNull(
                SystemSetting::findByKey($key),
                "Live setting $key must have a seeded DB row."
            );
        }
    }

    public function test_seeded_values_match_defaults(): void
    {
        $this->artisan('migrate');

        $defaults = app(AdminSettingsService::class)->getDefaults();

        foreach ($defaults as $key => $expected) {
            $this->assertSame(
                $expected,
                SystemSetting::findByKey($key)?->value,
                "Seeded value for $key must match AdminSettingsService::DEFAULTS."
            );
        }
    }

    public function test_migration_is_idempotent_and_preserves_admin_edits(): void
    {
        $this->artisan('migrate');

        $setting = SystemSetting::findByKey('login_lockout_attempts');
        $setting->update(['value' => 8]);

        // Re-running the seed migration's up() must not overwrite the admin-edited value.
        $migration = require database_path('migrations/2026_07_07_000001_seed_live_settings_defaults.php');
        $migration->up();
        $migration->up();

        $this->assertSame(8, SystemSetting::findByKey('login_lockout_attempts')->value);
    }
}

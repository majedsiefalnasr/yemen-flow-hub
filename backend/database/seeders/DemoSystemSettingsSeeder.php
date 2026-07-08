<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Database\Seeders\Concerns\GuardsDemoSeedEnvironment;
use Illuminate\Database\Seeder;

/**
 * Demo-only system setting overrides — never production-safe defaults.
 *
 * SystemSettingsSeeder already seeds production-safe defaults (including
 * duplicate_invoice_policy=warn). This seeder adds document_scan_enforced,
 * which only exists so QA/Playwright/PHPUnit can exercise scan-blocked
 * download behavior against the seeded scan_pending/scan_failed/scan_infected
 * anchors and bulk rows.
 *
 * Spec: backend/docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md § Demo system settings
 */
class DemoSystemSettingsSeeder extends Seeder
{
    use GuardsDemoSeedEnvironment;

    public function run(): void
    {
        $this->ensureDemoSeedAllowed();

        SystemSetting::query()->updateOrCreate(
            ['key' => 'document_scan_enforced'],
            ['value' => true, 'updated_by' => null],
        );
    }
}

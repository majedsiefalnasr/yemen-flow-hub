<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Duplicate invoice policy: 'warn' | 'block'
            ['key' => 'duplicate_invoice_policy', 'value' => 'warn'],

            // Claim TTL in minutes (support committee review claim)
            ['key' => 'claim_ttl_minutes', 'value' => 15],

            // Session inactivity timeout in minutes before auto-logout
            ['key' => 'session_timeout_minutes', 'value' => 30],

            // Maximum file upload size in megabytes
            ['key' => 'max_upload_size_mb', 'value' => 10],

            // Require MFA for all CBY (central bank) users
            ['key' => 'require_mfa_for_cby_users', 'value' => true],

            // Enable demo mode (role switcher visible in UI)
            ['key' => 'demo_mode_enabled', 'value' => true],

            // Minimum quorum of executive votes needed to close a session
            ['key' => 'voting_quorum_min', 'value' => 4],

            // Tie-breaking rule: 'director_decides' | 'reject_on_tie'
            ['key' => 'voting_tie_rule', 'value' => 'director_decides'],

            // Days before a DRAFT request is auto-expired (0 = disabled)
            ['key' => 'draft_auto_expire_days', 'value' => 0],

            // Notify via email in addition to in-app notifications
            ['key' => 'email_notifications_enabled', 'value' => false],

            // Maximum login failures before account lockout
            ['key' => 'max_login_failures', 'value' => 10],

            // Account lockout duration in minutes
            ['key' => 'lockout_duration_minutes', 'value' => 15],

            // Platform name shown in UI header and emails
            ['key' => 'platform_name', 'value' => 'اللجنة الوطنية لتنظيم وتمويل الواردات'],

            // Default UI language: 'ar' | 'en'
            ['key' => 'default_language', 'value' => 'ar'],

            // Supported currencies (shown in request wizard)
            ['key' => 'supported_currencies', 'value' => ['USD', 'EUR', 'SAR', 'AED', 'CNY']],

            // Audit log retention in days (0 = indefinite)
            ['key' => 'audit_retention_days', 'value' => 0],
        ];

        foreach ($settings as $row) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'updated_by' => null],
            );
        }
    }
}

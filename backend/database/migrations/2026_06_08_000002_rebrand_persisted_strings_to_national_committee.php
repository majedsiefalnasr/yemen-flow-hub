<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Epic 17-A (code-review decision #6): rebrand old-brand strings already
 * PERSISTED in the database on environments seeded before the rebrand.
 *
 * Seeder source code is already clean, but `system_settings` rows and the
 * `notification_template_versions` written on first seed retain the old brand
 * (NotificationTemplateSeeder uses firstOrCreate and skips existing versions).
 * This migration aligns those persisted rows so there is no mixed branding.
 *
 * Display-only string replacement — no schema/enum/structural change. One-way
 * (no data revert in down()).
 */
return new class extends Migration
{
    /**
     * @var array<string, string> old literal => canonical replacement
     */
    private array $replacements = [
        'البنك المركزي اليمني' => 'اللجنة الوطنية لتنظيم وتمويل الواردات',
        'Central Bank of Yemen' => 'The National Committee for Regulating & Financing Imports',
        'National Committee for Import Regulation and Financing' => 'The National Committee for Regulating & Financing Imports',
        'Yemen Flow Hub' => 'اللجنة الوطنية لتنظيم وتمويل الواردات',
    ];

    public function up(): void
    {
        $this->rebrandColumn('notification_template_versions', 'subject');
        $this->rebrandColumn('notification_template_versions', 'body');
        $this->rebrandColumn('system_settings', 'value');
    }

    public function down(): void
    {
        // Intentionally irreversible: the old brand is retired, no data revert.
    }

    private function rebrandColumn(string $table, string $column): void
    {
        DB::table($table)
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row) use ($table, $column): void {
                $value = $row->{$column};

                if (! is_string($value) || $value === '') {
                    return;
                }

                $updated = strtr($value, $this->replacements);

                if ($updated !== $value) {
                    DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                }
            });
    }
};

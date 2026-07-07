<?php

use App\Models\SystemSetting;
use App\Services\Settings\AdminSettingsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = app(AdminSettingsService::class)->getDefaults();

        foreach ($defaults as $key => $value) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    public function down(): void
    {
        // Rows intentionally not dropped on rollback — they hold admin edits.
    }
};

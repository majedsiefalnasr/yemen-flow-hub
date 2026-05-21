<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'duplicate_invoice_policy'],
            ['value' => 'warn', 'updated_by' => null]
        );
    }
}

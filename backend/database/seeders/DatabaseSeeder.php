<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([GovernanceSeeder::class]);
        $this->command?->info('Seeded governance identity.');

        $this->call([ScreenPermissionSeeder::class]);
        $this->command?->info('Seeded screen permissions catalog.');

        $this->call([ReferenceDataSeeder::class]);
        $this->command?->info('Seeded reference data tables.');

        $this->call([WorkflowActionSeeder::class]);
        $this->command?->info('Seeded workflow actions catalog.');

        $this->call([ImportFinancingWorkflowSeeder::class]);
        $this->command?->info('Seeded Import Financing workflow.');

        $this->call([BankSeeder::class]);
        $this->command?->info('Seeded banks.');

        $this->call([UserSeeder::class]);
        $this->command?->info('Seeded users.');

        $this->call([MerchantSeeder::class]);
        $this->command?->info('Seeded merchants.');

        $demoSeedAllowed = config('demo.seed_demo_data')
            && in_array(app()->environment(), config('demo.allowed_seed_environments', []), true);

        if ($demoSeedAllowed) {
            $this->call([EngineRequestAnchorSeeder::class]);
            $this->command?->info('Seeded engine request anchors.');

            $this->call([EngineRequestBulkSeeder::class]);
            $this->command?->info('Seeded engine request bulk data (skipped when DEMO_SEED_SIZE=minimal).');
        }

        $this->call([AuditLogSeeder::class]);
        $this->command?->info('Seeded login audit logs.');

        $this->call([SystemSettingsSeeder::class]);
        $this->command?->info('Seeded system settings.');

        if ($demoSeedAllowed) {
            $this->call([DemoSystemSettingsSeeder::class]);
            $this->command?->info('Seeded demo system settings.');
        }

        $this->call([NotificationTemplateSeeder::class]);
        $this->command?->info('Seeded notification templates.');

        if ($demoSeedAllowed) {
            $this->call([EngineAuxiliaryDemoSeeder::class]);
            $this->command?->info('Seeded engine auxiliary demo data.');
        }
    }
}

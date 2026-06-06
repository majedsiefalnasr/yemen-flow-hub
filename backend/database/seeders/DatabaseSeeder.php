<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class]);
        $this->command?->info('Seeded permissions matrix.');

        $this->call([DocumentTypeSeeder::class]);
        $this->command?->info('Seeded document types.');

        $this->call([BankSeeder::class]);
        $this->command?->info('Seeded banks.');

        $this->call([UserSeeder::class]);
        $this->command?->info('Seeded users.');

        $this->call([MerchantSeeder::class]);
        $this->command?->info('Seeded merchants.');

        $this->call([ImportRequestSeeder::class]);
        $this->command?->info('Seeded requests and linked workflow artifacts.');

        $this->call([AuditLogSeeder::class]);
        $this->command?->info('Seeded login audit logs.');

        $this->call([NotificationSeeder::class]);
        $this->command?->info('Seeded notifications.');

        $this->call([SystemSettingsSeeder::class]);
        $this->command?->info('Seeded system settings.');

        $this->call([NotificationTemplateSeeder::class]);
        $this->command?->info('Seeded notification templates.');
    }
}

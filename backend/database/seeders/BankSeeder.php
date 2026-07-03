<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = Organization::query()->where('code', 'commercial_banks')->value('id');

        // Two active commercial banks. Each gets its own full role set of users and
        // four merchants, so org-scoped visibility can be tested per bank.
        Bank::query()->upsert([
            ['organization_id' => $organizationId, 'code' => 'YBRD', 'name' => 'البنك اليمني للإنشاء والتعمير', 'status' => 'ACTIVE', 'is_active' => true],
            ['organization_id' => $organizationId, 'code' => 'TIIB', 'name' => 'بنك التضامن الإسلامي الدولي', 'status' => 'ACTIVE', 'is_active' => true],
        ], ['code'], ['organization_id', 'name', 'status', 'is_active']);
    }
}

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
        Bank::query()->upsert([
            ['organization_id' => $organizationId, 'code' => 'YBRD', 'name' => 'البنك اليمني للإنشاء والتعمير', 'status' => 'ACTIVE', 'is_active' => true],
            ['organization_id' => $organizationId, 'code' => 'TIIB', 'name' => 'بنك التضامن الإسلامي الدولي', 'status' => 'ACTIVE', 'is_active' => true],
            ['organization_id' => $organizationId, 'code' => 'YCB', 'name' => 'البنك التجاري اليمني', 'status' => 'ACTIVE', 'is_active' => true],
            ['organization_id' => $organizationId, 'code' => 'SIB', 'name' => 'بنك سبأ الإسلامي', 'status' => 'ACTIVE', 'is_active' => true],
            ['organization_id' => $organizationId, 'code' => 'NBY', 'name' => 'البنك الأهلي اليمني', 'status' => 'SUSPENDED', 'is_active' => false],
        ], ['code'], ['organization_id', 'name', 'status', 'is_active']);
    }
}

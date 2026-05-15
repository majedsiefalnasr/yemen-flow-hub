<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        Bank::query()->upsert([
            ['code' => 'YBRD', 'name' => 'البنك اليمني للإنشاء والتعمير', 'is_active' => true],
            ['code' => 'TIIB', 'name' => 'بنك التضامن الإسلامي الدولي', 'is_active' => true],
            ['code' => 'YCB', 'name' => 'البنك التجاري اليمني', 'is_active' => true],
            ['code' => 'SIB', 'name' => 'بنك سبأ الإسلامي', 'is_active' => true],
            ['code' => 'NBY', 'name' => 'البنك الأهلي اليمني', 'is_active' => false],
        ], ['code'], ['name', 'is_active']);
    }
}

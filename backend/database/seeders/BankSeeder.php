<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        Bank::query()->upsert([
            ['code' => 'YBRD', 'name_ar' => 'البنك اليمني للإنشاء والتعمير', 'name_en' => 'Yemen Bank for Reconstruction and Development', 'is_active' => true],
            ['code' => 'TIIB', 'name_ar' => 'بنك التضامن الإسلامي الدولي', 'name_en' => 'Tadhamon International Islamic Bank', 'is_active' => true],
            ['code' => 'YCB', 'name_ar' => 'البنك التجاري اليمني', 'name_en' => 'Yemen Commercial Bank', 'is_active' => true],
            ['code' => 'SIB', 'name_ar' => 'بنك سبأ الإسلامي', 'name_en' => 'Saba Islamic Bank', 'is_active' => true],
            ['code' => 'NBY', 'name_ar' => 'البنك الأهلي اليمني', 'name_en' => 'National Bank of Yemen', 'is_active' => false],
        ], ['code'], ['name_ar', 'name_en', 'is_active']);
    }
}

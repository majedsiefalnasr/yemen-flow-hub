<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $cbyUsers = [
            ['name' => 'ياسر الحضرمي', 'email' => 'admin@cby.gov.ye', 'role' => UserRole::CBY_ADMIN],
            ['name' => 'محمد الشامي', 'email' => 'support1@cby.gov.ye', 'role' => UserRole::SUPPORT_COMMITTEE],
            ['name' => 'نسيم العمري', 'email' => 'support2@cby.gov.ye', 'role' => UserRole::SUPPORT_COMMITTEE],
            ['name' => 'د. هدى الإرياني', 'email' => 'director@cby.gov.ye', 'role' => UserRole::EXECUTIVE_DIRECTOR],
            ['name' => 'م. سامي الذماري', 'email' => 'exec1@cby.gov.ye', 'role' => UserRole::EXECUTIVE_MEMBER],
            ['name' => 'د. ندى الكبسي', 'email' => 'exec2@cby.gov.ye', 'role' => UserRole::EXECUTIVE_MEMBER],
            ['name' => 'أ. فهد الشرعبي', 'email' => 'exec3@cby.gov.ye', 'role' => UserRole::EXECUTIVE_MEMBER],
            ['name' => 'د. أمينة العزب', 'email' => 'exec4@cby.gov.ye', 'role' => UserRole::EXECUTIVE_MEMBER],
            ['name' => 'م. خالد الأنسي', 'email' => 'exec5@cby.gov.ye', 'role' => UserRole::EXECUTIVE_MEMBER],
            ['name' => 'محمود الذيباني', 'email' => 'exec6@cby.gov.ye', 'role' => UserRole::EXECUTIVE_MEMBER],
        ];

        foreach ($cbyUsers as $row) {
            User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $password,
                    'role' => $row['role'],
                    'bank_id' => null,
                    'is_active' => true,
                ]
            );
        }

        $bankSpecific = [
            'YBRD' => [
                ['role' => UserRole::BANK_MANAGER, 'name' => 'أحمد المقطري', 'email' => 'manager@ybrd.com.ye'],
                ['role' => UserRole::DATA_ENTRY, 'name' => 'علي القاضي', 'email' => 'entry@ybrd.com.ye'],
                ['role' => UserRole::BANK_REVIEWER, 'name' => 'نوال الحاج', 'email' => 'reviewer@ybrd.com.ye'],
                ['role' => UserRole::SWIFT_OFFICER, 'name' => 'سامي العتمي', 'email' => 'swift@ybrd.com.ye'],
            ],
        ];

        $namePool = [
            'خالد الشميري', 'رامي القدسي', 'حسام الذبحاني', 'سليمان ناصر',
            'هشام الوصابي', 'أنور اليافعي', 'عبدالملك السياني', 'نبيل المقطري',
            'ليلى الحكيمي', 'غادة العماري', 'صفاء المؤيد', 'سمر الديلمي',
            'وفاء العريقي', 'عبير الآنسي', 'أمل الكبسي', 'رنا الشعيبي',
        ];
        $nameIdx = 0;

        $activeBanks = Bank::query()->where('is_active', true)->orderBy('id')->get();
        foreach ($activeBanks as $bank) {
            $code = strtolower($bank->code);
            $rows = $bankSpecific[$bank->code] ?? [
                ['role' => UserRole::BANK_MANAGER, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "manager@{$code}.com.ye"],
                ['role' => UserRole::DATA_ENTRY, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "entry@{$code}.com.ye"],
                ['role' => UserRole::BANK_REVIEWER, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "reviewer@{$code}.com.ye"],
                ['role' => UserRole::SWIFT_OFFICER, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "swift@{$code}.com.ye"],
            ];

            foreach ($rows as $row) {
                User::query()->updateOrCreate(
                    ['email' => $row['email']],
                    [
                        'name' => $row['name'],
                        'password' => $password,
                        'role' => $row['role'],
                        'bank_id' => $bank->id,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command?->line('✓ CBY users:');
        $this->command?->line('  - 1 CBY_ADMIN');
        $this->command?->line('  - 2 SUPPORT_COMMITTEE');
        $this->command?->line('  - 6 EXECUTIVE_MEMBER');
        $this->command?->line('  - 1 EXECUTIVE_DIRECTOR');
        $this->command?->line('✓ Bank users (4 banks × 4 roles):');
        $this->command?->line('  - 4 BANK_MANAGER');
        $this->command?->line('  - 4 DATA_ENTRY');
        $this->command?->line('  - 4 BANK_REVIEWER');
        $this->command?->line('  - 4 SWIFT_OFFICER');
        $this->command?->line('✓ Total: 26 users');
    }
}

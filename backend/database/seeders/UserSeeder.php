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

        // CBY users — canonical 8-role model, extra exec members for voting quorum
        $cbyUsers = [
            ['name' => 'ياسر الحضرمي',   'email' => 'admin@cby.gov.ye',    'role' => UserRole::CBY_ADMIN,          'phone' => '+967 77 123 4567'],
            ['name' => 'محمد الشامي',     'email' => 'support1@cby.gov.ye', 'role' => UserRole::SUPPORT_COMMITTEE,  'phone' => '+967 71 234 5678'],
            ['name' => 'نسيم العمري',     'email' => 'support2@cby.gov.ye', 'role' => UserRole::SUPPORT_COMMITTEE,  'phone' => '+967 73 345 6789'],
            ['name' => 'د. هدى الإرياني','email' => 'director@cby.gov.ye', 'role' => UserRole::COMMITTEE_DIRECTOR, 'phone' => '+967 77 456 7890'],
            ['name' => 'م. سامي الذماري','email' => 'exec1@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 71 567 8901'],
            ['name' => 'د. ندى الكبسي',  'email' => 'exec2@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 73 678 9012'],
            ['name' => 'أ. فهد الشرعبي', 'email' => 'exec3@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 77 789 0123'],
            ['name' => 'د. أمينة العزب', 'email' => 'exec4@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 71 890 1234'],
            ['name' => 'م. خالد الأنسي', 'email' => 'exec5@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 73 901 2345'],
            ['name' => 'محمود الذيباني', 'email' => 'exec6@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 77 012 3456'],
        ];

        foreach ($cbyUsers as $row) {
            User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name'             => $row['name'],
                    'password'         => $password,
                    'role'             => $row['role'],
                    'bank_id'          => null,
                    'is_active'        => true,
                    'mfa_enabled'      => true,
                    'phone'            => $row['phone'],
                    'user_preferences' => $this->defaultPreferences($row['role']),
                ]
            );
        }

        // Bank users — 5 canonical bank roles per bank (BANK_ADMIN, 2×DATA_ENTRY, BANK_REVIEWER, SWIFT_OFFICER)
        // Two DATA_ENTRY users per bank give the request seeder variety in request creators.
        $bankSpecific = [
            'YBRD' => [
                ['role' => UserRole::BANK_ADMIN,    'name' => 'فاطمة المقطري',   'email' => 'admin@ybrd.com.ye'],
                ['role' => UserRole::DATA_ENTRY,    'name' => 'علي القاضي',      'email' => 'entry@ybrd.com.ye'],
                ['role' => UserRole::DATA_ENTRY,    'name' => 'مريم الحارثي',    'email' => 'entry2@ybrd.com.ye'],
                ['role' => UserRole::BANK_REVIEWER, 'name' => 'نوال الحاج',      'email' => 'reviewer@ybrd.com.ye'],
                ['role' => UserRole::SWIFT_OFFICER, 'name' => 'سامي العتمي',     'email' => 'swift@ybrd.com.ye'],
            ],
        ];

        $namePool = [
            'خالد الشميري', 'رامي القدسي', 'حسام الذبحاني', 'سليمان ناصر',
            'هشام الوصابي', 'أنور اليافعي', 'عبدالملك السياني', 'نبيل المقطري',
            'ليلى الحكيمي', 'غادة العماري', 'صفاء المؤيد', 'سمر الديلمي',
            'وفاء العريقي', 'عبير الآنسي', 'أمل الكبسي', 'رنا الشعيبي',
            'تهاني الجابري', 'داود العمراني', 'منى القحطاني', 'يوسف الحامدي',
        ];
        $nameIdx = 0;

        $activeBanks = Bank::query()->where('is_active', true)->orderBy('id')->get();
        foreach ($activeBanks as $bank) {
            $code = strtolower($bank->code);
            $rows = $bankSpecific[$bank->code] ?? [
                ['role' => UserRole::BANK_ADMIN,    'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "admin@{$code}.com.ye"],
                ['role' => UserRole::DATA_ENTRY,    'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "entry@{$code}.com.ye"],
                ['role' => UserRole::DATA_ENTRY,    'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "entry2@{$code}.com.ye"],
                ['role' => UserRole::BANK_REVIEWER, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "reviewer@{$code}.com.ye"],
                ['role' => UserRole::SWIFT_OFFICER, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "swift@{$code}.com.ye"],
            ];

            foreach ($rows as $idx => $row) {
                User::query()->updateOrCreate(
                    ['email' => $row['email']],
                    [
                        'name'             => $row['name'],
                        'password'         => $password,
                        'role'             => $row['role'],
                        'bank_id'          => $bank->id,
                        'is_active'        => true,
                        'mfa_enabled'      => false,
                        'phone'            => $this->generatePhone($bank->code, $idx),
                        'user_preferences' => $this->defaultPreferences($row['role']),
                    ]
                );
            }
        }

        $this->command?->line('✓ Users seeded with phone, mfa_enabled, and notification preferences.');
        $this->command?->line('✓ CBY users:');
        $this->command?->line('  - 1 CBY_ADMIN');
        $this->command?->line('  - 2 SUPPORT_COMMITTEE');
        $this->command?->line('  - 1 COMMITTEE_DIRECTOR');
        $this->command?->line('  - 6 EXECUTIVE_MEMBER');
        $this->command?->line('✓ Bank users (4 active banks × 5 roles):');
        $this->command?->line('  - 4 BANK_ADMIN');
        $this->command?->line('  - 8 DATA_ENTRY (2 per bank)');
        $this->command?->line('  - 4 BANK_REVIEWER');
        $this->command?->line('  - 4 SWIFT_OFFICER');
        $this->command?->line('✓ Total: ~30 users');
    }

    private function defaultPreferences(UserRole $role): array
    {
        $notifMap = [
            UserRole::DATA_ENTRY->value        => ['request_approved' => true, 'request_rejected' => true, 'request_returned' => true, 'customs_issued' => true],
            UserRole::BANK_REVIEWER->value     => ['request_submitted' => true, 'request_rejected' => true, 'customs_issued' => true],
            UserRole::BANK_ADMIN->value        => ['request_submitted' => true, 'request_rejected' => true, 'request_returned' => true],
            UserRole::SUPPORT_COMMITTEE->value => ['request_submitted' => true, 'claim_released' => true],
            UserRole::SWIFT_OFFICER->value     => ['swift_upload_requested' => true],
            UserRole::EXECUTIVE_MEMBER->value  => ['voting_opened' => true, 'request_rejected' => true],
            UserRole::COMMITTEE_DIRECTOR->value=> ['swift_upload_requested' => true, 'voting_opened' => true, 'customs_issued' => true],
            UserRole::CBY_ADMIN->value         => ['request_submitted' => true, 'request_rejected' => true, 'claim_released' => true, 'customs_issued' => true],
        ];

        return [
            'language'                 => 'ar',
            'dashboard_view'           => 'normal',
            'table_density'            => 'normal',
            'page_size'                => 25,
            'default_filters'          => [],
            'notification_preferences' => $notifMap[$role->value] ?? [],
        ];
    }

    private function generatePhone(string $bankCode, int $idx): string
    {
        $prefixes = ['77', '71', '73', '70', '78'];
        $prefix = $prefixes[($idx + crc32($bankCode)) % count($prefixes)];
        $mid = str_pad((string) (($idx * 137 + crc32($bankCode)) % 1000), 3, '0', STR_PAD_LEFT);
        $end = str_pad((string) (($idx * 251 + crc32($bankCode)) % 10000), 4, '0', STR_PAD_LEFT);
        return "+967 {$prefix} {$mid} {$end}";
    }
}

<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // National-committee + system-admin users. One holder per committee role;
        // extra exec members keep the voting quorum for the EXEC stage. The
        // dedicated fx_confirm holder is seeded separately below (no legacy enum
        // maps to it).
        $cbyUsers = [
            ['name' => 'ياسر الحضرمي',   'email' => 'admin@cby.gov.ye',    'role' => UserRole::CBY_ADMIN,          'phone' => '+967 77 123 4567'],
            ['name' => 'محمد الشامي',     'email' => 'support1@cby.gov.ye', 'role' => UserRole::SUPPORT_COMMITTEE,  'phone' => '+967 71 234 5678'],
            ['name' => 'نسيم العمري',     'email' => 'support2@cby.gov.ye', 'role' => UserRole::SUPPORT_COMMITTEE,  'phone' => '+967 73 345 6789'],
            ['name' => 'د. هدى الإرياني', 'email' => 'director@cby.gov.ye', 'role' => UserRole::COMMITTEE_DIRECTOR, 'phone' => '+967 77 456 7890'],
            ['name' => 'م. سامي الذماري', 'email' => 'exec1@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 71 567 8901'],
            ['name' => 'د. ندى الكبسي',  'email' => 'exec2@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 73 678 9012'],
            ['name' => 'أ. فهد الشرعبي', 'email' => 'exec3@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 77 789 0123'],
            ['name' => 'د. أمينة العزب', 'email' => 'exec4@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 71 890 1234'],
            ['name' => 'م. خالد الأنسي', 'email' => 'exec5@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 73 901 2345'],
            ['name' => 'محمود الذيباني', 'email' => 'exec6@cby.gov.ye',    'role' => UserRole::EXECUTIVE_MEMBER,   'phone' => '+967 77 012 3456'],
        ];

        foreach ($cbyUsers as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $password,
                    'role' => $row['role'],
                    'bank_id' => null,
                    'is_active' => true,
                    'mfa_enabled' => true,
                    'phone' => $row['phone'],
                    'user_preferences' => $this->defaultPreferences($row['role']),
                ]
            );
            $this->assignIdentity($user, $row['role']);
        }

        // Dedicated fx_confirm holder for the FX_CONFIRM stage. No legacy UserRole
        // maps to it, so its governance identity (org/team/role) is set explicitly.
        $this->seedFxConfirmUser($password);

        // Bank users — one holder per bank role (BANK_ADMIN, DATA_ENTRY,
        // BANK_REVIEWER, SWIFT_OFFICER). Both banks get an explicit block so the
        // seeded names match the demo screenshots.
        $bankSpecific = [
            'YBRD' => [
                ['role' => UserRole::BANK_ADMIN,    'name' => 'فاطمة المقطري', 'email' => 'admin@ybrd.com.ye'],
                ['role' => UserRole::DATA_ENTRY,    'name' => 'علي القاضي',    'email' => 'entry@ybrd.com.ye'],
                ['role' => UserRole::BANK_REVIEWER, 'name' => 'نوال الحاج',    'email' => 'reviewer@ybrd.com.ye'],
                ['role' => UserRole::SWIFT_OFFICER, 'name' => 'سامي العتمي',   'email' => 'swift@ybrd.com.ye'],
            ],
            'TIIB' => [
                ['role' => UserRole::BANK_ADMIN,    'name' => 'عبير الآنسي',   'email' => 'admin@tiib.com.ye'],
                ['role' => UserRole::DATA_ENTRY,    'name' => 'رامي القدسي',   'email' => 'entry@tiib.com.ye'],
                ['role' => UserRole::BANK_REVIEWER, 'name' => 'سليمان ناصر',   'email' => 'reviewer@tiib.com.ye'],
                ['role' => UserRole::SWIFT_OFFICER, 'name' => 'هشام الوصابي',  'email' => 'swift@tiib.com.ye'],
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
                ['role' => UserRole::BANK_REVIEWER, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "reviewer@{$code}.com.ye"],
                ['role' => UserRole::SWIFT_OFFICER, 'name' => $namePool[$nameIdx++ % count($namePool)], 'email' => "swift@{$code}.com.ye"],
            ];

            foreach ($rows as $idx => $row) {
                $user = User::query()->updateOrCreate(
                    ['email' => $row['email']],
                    [
                        'name' => $row['name'],
                        'password' => $password,
                        'role' => $row['role'],
                        'bank_id' => $bank->id,
                        'is_active' => true,
                        'mfa_enabled' => false,
                        'phone' => $this->generatePhone($bank->code, $idx),
                        'user_preferences' => $this->defaultPreferences($row['role']),
                    ]
                );
                $this->assignIdentity($user, $row['role']);
            }
        }

        $this->command?->line('✓ Users seeded with phone, mfa_enabled, and notification preferences.');
        $this->command?->line('✓ National committee + system admin users:');
        $this->command?->line('  - 1 CBY_ADMIN (system_admin)');
        $this->command?->line('  - 2 SUPPORT_COMMITTEE (support)');
        $this->command?->line('  - 1 COMMITTEE_DIRECTOR (committee_director)');
        $this->command?->line('  - 6 EXECUTIVE_MEMBER (committee_manager)');
        $this->command?->line('  - 1 FX_CONFIRM (fxconfirm@cby.gov.ye)');
        $this->command?->line('✓ Bank users (2 active banks × 4 roles):');
        $this->command?->line('  - 2 BANK_ADMIN');
        $this->command?->line('  - 2 DATA_ENTRY (intake)');
        $this->command?->line('  - 2 BANK_REVIEWER (internal_reviewer)');
        $this->command?->line('  - 2 SWIFT_OFFICER (fx_swift)');
        $this->command?->line('✓ Total: 19 users');
    }

    /**
     * Seed the dedicated fx_confirm holder for the FX_CONFIRM stage.
     *
     * No legacy UserRole enum case maps to fx_confirm, so its governance identity
     * (organization national_committee / team fx_confirmation / role fx_confirm)
     * is wired explicitly here instead of through assignIdentity(). The legacy
     * `role` column is set to COMMITTEE_DIRECTOR only to satisfy the not-null enum
     * column; authority derives entirely from the governance identity below.
     */
    private function seedFxConfirmUser(string $password): void
    {
        $organization = Organization::query()->where('code', 'national_committee')->firstOrFail();
        $team = Team::query()
            ->whereBelongsTo($organization)
            ->where('code', 'fx_confirmation')
            ->firstOrFail();
        $role = Role::query()
            ->whereBelongsTo($organization)
            ->where('code', 'fx_confirm')
            ->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => 'fxconfirm@cby.gov.ye'],
            [
                'name' => 'سلوى المروني',
                'password' => $password,
                'role' => UserRole::COMMITTEE_DIRECTOR,
                'organization_id' => $organization->id,
                'bank_id' => null,
                'is_active' => true,
                'mfa_enabled' => true,
                'phone' => '+967 77 654 3210',
                'user_preferences' => $this->defaultPreferences(UserRole::COMMITTEE_DIRECTOR),
            ]
        );

        $user->teams()->sync([$team->id]);
        $user->roles()->sync([$role->id]);
    }

    private function defaultPreferences(UserRole $role): array
    {
        $notifMap = [
            UserRole::DATA_ENTRY->value => ['request_approved' => true, 'request_rejected' => true, 'request_returned' => true, 'customs_issued' => true],
            UserRole::BANK_REVIEWER->value => ['request_submitted' => true, 'request_rejected' => true, 'customs_issued' => true],
            UserRole::BANK_ADMIN->value => ['request_submitted' => true, 'request_rejected' => true, 'request_returned' => true],
            UserRole::SUPPORT_COMMITTEE->value => ['request_submitted' => true, 'claim_released' => true],
            UserRole::SWIFT_OFFICER->value => ['swift_upload_requested' => true],
            UserRole::EXECUTIVE_MEMBER->value => ['voting_opened' => true, 'request_rejected' => true],
            UserRole::COMMITTEE_DIRECTOR->value => ['swift_upload_requested' => true, 'voting_opened' => true, 'customs_issued' => true],
            UserRole::CBY_ADMIN->value => ['request_submitted' => true, 'request_rejected' => true, 'claim_released' => true, 'customs_issued' => true],
        ];

        return [
            'language' => 'ar',
            'dashboard_view' => 'normal',
            'table_density' => 'normal',
            'page_size' => 25,
            'default_filters' => [],
            'notification_preferences' => $notifMap[$role->value] ?? [],
        ];
    }

    private function assignIdentity(User $user, UserRole $legacyRole): void
    {
        $map = [
            UserRole::DATA_ENTRY->value => ['commercial_banks', 'entry', 'intake', true],
            UserRole::BANK_REVIEWER->value => ['commercial_banks', 'internal_review', 'internal_reviewer', true],
            UserRole::BANK_ADMIN->value => ['commercial_banks', 'bank_admin', 'bank_admin', true],
            UserRole::SWIFT_OFFICER->value => ['commercial_banks', 'fx_ops', 'fx_swift', true],
            UserRole::SUPPORT_COMMITTEE->value => ['national_committee', 'support', 'support', false],
            UserRole::EXECUTIVE_MEMBER->value => ['national_committee', 'executive', 'committee_manager', false],
            UserRole::COMMITTEE_DIRECTOR->value => ['national_committee', 'executive', 'committee_director', false],
            UserRole::CBY_ADMIN->value => ['system_administration', 'administration', 'system_admin', false],
        ];

        [$organizationCode, $teamCode, $roleCode, $keepsBank] = $map[$legacyRole->value];
        $organization = Organization::query()->where('code', $organizationCode)->firstOrFail();
        $team = Team::query()
            ->whereBelongsTo($organization)
            ->where('code', $teamCode)
            ->firstOrFail();
        $role = Role::query()
            ->whereBelongsTo($organization)
            ->where('code', $roleCode)
            ->firstOrFail();

        $user->forceFill([
            'organization_id' => $organization->id,
            'bank_id' => $keepsBank ? $user->bank_id : null,
        ])->save();
        $user->teams()->sync([$team->id]);
        $user->roles()->sync([$role->id]);
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

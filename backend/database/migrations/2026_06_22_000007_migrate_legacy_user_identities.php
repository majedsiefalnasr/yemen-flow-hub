<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $organizations = [
            'commercial_banks' => 'البنوك التجارية',
            'national_committee' => 'اللجنة الوطنية لتمويل الواردات',
            'system_administration' => 'إدارة النظام',
        ];

        foreach ($organizations as $code => $name) {
            DB::table('organizations')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'is_system' => true, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $organizationIds = DB::table('organizations')->pluck('id', 'code');
        $teams = [
            ['commercial_banks', 'entry', 'فريق الإدخال'],
            ['commercial_banks', 'internal_review', 'فريق المراجعة الداخلية'],
            ['commercial_banks', 'fx_ops', 'فريق عمليات الصرف/السويفت'],
            ['commercial_banks', 'bank_admin', 'إدارة البنك'],
            ['national_committee', 'support', 'اللجنة المساندة'],
            ['national_committee', 'executive', 'اللجنة التنفيذية'],
            ['national_committee', 'fx_confirmation', 'تأكيد المصارفة الخارجية'],
            ['system_administration', 'administration', 'إدارة النظام'],
        ];
        $roles = [
            ['commercial_banks', 'intake', 'موظف الإدخال'],
            ['commercial_banks', 'internal_reviewer', 'المراجع الداخلي'],
            ['commercial_banks', 'bank_admin', 'مسؤول البنك'],
            ['commercial_banks', 'fx_swift', 'موظف الصرف/السويفت'],
            ['national_committee', 'support', 'عضو اللجنة المساندة'],
            ['national_committee', 'committee_manager', 'مدير اللجنة التنفيذية'],
            ['national_committee', 'fx_confirm', 'موظف تأكيد المصارفة'],
            ['system_administration', 'system_admin', 'مسؤول النظام'],
        ];

        foreach ($teams as [$organizationCode, $code, $name]) {
            DB::table('teams')->updateOrInsert(
                ['organization_id' => $organizationIds[$organizationCode], 'code' => $code],
                ['name' => $name, 'is_system' => true, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }
        foreach ($roles as [$organizationCode, $code, $name]) {
            DB::table('roles')->updateOrInsert(
                ['organization_id' => $organizationIds[$organizationCode], 'code' => $code],
                ['name' => $name, 'is_system' => true, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $identityMap = [
            'DATA_ENTRY' => ['commercial_banks', 'entry', 'intake', true],
            'BANK_REVIEWER' => ['commercial_banks', 'internal_review', 'internal_reviewer', true],
            'BANK_ADMIN' => ['commercial_banks', 'bank_admin', 'bank_admin', true],
            'SWIFT_OFFICER' => ['commercial_banks', 'fx_ops', 'fx_swift', true],
            'SUPPORT_COMMITTEE' => ['national_committee', 'support', 'support', false],
            'EXECUTIVE_MEMBER' => ['national_committee', 'executive', 'committee_manager', false],
            'COMMITTEE_DIRECTOR' => ['national_committee', 'executive', 'committee_manager', false],
            'CBY_ADMIN' => ['system_administration', 'administration', 'system_admin', false],
        ];

        DB::table('users')->orderBy('id')->each(function (object $user) use ($identityMap, $organizationIds, $now): void {
            $legacyRole = is_string($user->role) ? $user->role : (string) $user->role;
            if ($legacyRole === '' || ! isset($identityMap[$legacyRole])) {
                // Unknown/NULL legacy role: leave the user untouched rather than
                // half-migrating them. They keep their existing identity columns.
                return;
            }

            [$organizationCode, $teamCode, $roleCode, $keepsBank] = $identityMap[$legacyRole];
            $organizationId = $organizationIds[$organizationCode];
            $teamId = DB::table('teams')
                ->where('organization_id', $organizationId)
                ->where('code', $teamCode)
                ->value('id');
            $roleId = DB::table('roles')
                ->where('organization_id', $organizationId)
                ->where('code', $roleCode)
                ->value('id');

            // Fail closed: never write a NULL team/role FK into the join tables.
            if ($teamId === null || $roleId === null) {
                throw new RuntimeException("Missing seeded team/role for legacy role [{$legacyRole}].");
            }

            // Each user is migrated atomically so a mid-row failure never leaves a
            // user with an org set but no team/role pivot.
            DB::transaction(function () use ($user, $organizationId, $teamId, $roleId, $keepsBank, $now): void {
                DB::table('users')->where('id', $user->id)->update([
                    'organization_id' => $organizationId,
                    'bank_id' => $keepsBank ? $user->bank_id : null,
                    'updated_at' => $now,
                ]);
                DB::table('user_teams')->updateOrInsert(
                    ['user_id' => $user->id, 'team_id' => $teamId],
                    ['updated_at' => $now, 'created_at' => $now]
                );
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $roleId],
                    ['updated_at' => $now, 'created_at' => $now]
                );
            });
        });
    }

    public function down(): void
    {
        DB::table('user_roles')->delete();
        DB::table('user_teams')->delete();
        DB::table('users')->update(['organization_id' => null]);
    }
};

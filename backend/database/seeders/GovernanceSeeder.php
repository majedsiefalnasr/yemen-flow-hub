<?php

namespace Database\Seeders;

use App\Enums\OrganizationClassification;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Seeder;

class GovernanceSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            'commercial_banks' => ['name' => 'البنوك التجارية', 'classification' => OrganizationClassification::BANKING_SECTOR],
            'national_committee' => ['name' => 'اللجنة الوطنية لتمويل الواردات', 'classification' => OrganizationClassification::NATIONAL_COMMITTEE],
            'system_administration' => ['name' => 'إدارة النظام', 'classification' => OrganizationClassification::OTHER],
        ];

        foreach ($organizations as $code => $attributes) {
            Organization::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $attributes['name'],
                    'classification' => $attributes['classification'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }

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
            ['national_committee', 'committee_manager', 'عضو اللجنة التنفيذية'],
            ['national_committee', 'committee_director', 'مدير اللجنة التنفيذية (مدير)'],
            ['national_committee', 'fx_confirm', 'موظف تأكيد المصارفة'],
            ['system_administration', 'system_admin', 'مسؤول النظام'],
        ];

        foreach ($teams as [$organizationCode, $code, $name]) {
            $organization = Organization::query()->where('code', $organizationCode)->firstOrFail();
            Team::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $code],
                ['name' => $name, 'is_system' => true, 'is_active' => true]
            );
        }

        foreach ($roles as [$organizationCode, $code, $name]) {
            $organization = Organization::query()->where('code', $organizationCode)->firstOrFail();
            Role::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'code' => $code],
                ['name' => $name, 'is_system' => true, 'is_active' => true]
            );
        }
    }
}

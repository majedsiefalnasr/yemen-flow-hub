<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScreenPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $screens = [
            'organizations' => 'المنظمات',
            'teams' => 'الفرق',
            'roles' => 'الأدوار والصلاحيات',
            'banks' => 'البنوك',
            'users' => 'المستخدمون',
            'staff' => 'الموظفون',
            'merchants' => 'المستوردون',
            'workflow_designer' => 'مصمم سير العمل',
            'requests' => 'الطلبات',
            'reports' => 'التقارير',
            'audit' => 'سجل التدقيق',
            'reference_data' => 'البيانات المرجعية',
            'screen_permissions' => 'صلاحيات الشاشات',
            'notifications' => 'الإشعارات',
            'settings' => 'الإعدادات',
            // Phase D0 dashboard-family capabilities. `system_dashboard` gates the
            // platform governance/analytics dashboard + its APIs; `org_analytics`
            // gates the organization-scoped analytics dashboard + its APIs. Workflow
            // users hold neither and fall through to the operational MyWorkDashboard.
            'system_dashboard' => 'لوحة إدارة النظام',
            'org_analytics' => 'تحليلات المنظمة',
        ];

        foreach ($screens as $key => $label) {
            Screen::query()->updateOrCreate(['key' => $key], ['label' => $label]);
        }

        // Map governance role codes → screen capabilities.
        // Format: role_code => [ screen_key => [capabilities] ]
        $grants = [
            // ── Commercial Banks ──────────────────────────────────
            'intake' => [
                'merchants' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'internal_reviewer' => [
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'bank_admin' => [
                'merchants' => ['VIEW', 'MANAGE'],
                'users' => ['VIEW', 'MANAGE'],
                'staff' => ['VIEW'],
                'reports' => ['VIEW'],
                // SEC-002: bank-scoped audit visibility, gated by audit_logs.bank_id.
                'audit' => ['VIEW'],
                // Routes Bank Admin to the organization-scoped analytics dashboard (D0).
                'org_analytics' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'fx_swift' => [
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],

            // ── National Committee ────────────────────────────────
            'support' => [
                'audit' => ['VIEW'],
                'reports' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'committee_manager' => [
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'committee_director' => [
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW', 'EXPORT'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'fx_confirm' => [
                'reports' => ['VIEW'],
                'audit' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],

            // ── System Administration ─────────────────────────────
            // system_admin is intentionally restricted on merchants: VIEW +
            // EXPORT only, never MANAGE. Enforced again in code
            // (PermissionService::userHasCapability) so it cannot be
            // bypassed by a future edit to this seeder.
            'system_admin' => [
                'organizations' => ['VIEW', 'MANAGE'],
                'teams' => ['VIEW', 'MANAGE'],
                'roles' => ['VIEW', 'MANAGE'],
                'banks' => ['VIEW', 'MANAGE'],
                'users' => ['VIEW', 'MANAGE'],
                'merchants' => ['VIEW', 'EXPORT'],
                'workflow_designer' => ['VIEW', 'MANAGE'],
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW', 'EXPORT'],
                'reference_data' => ['VIEW', 'MANAGE'],
                'screen_permissions' => ['VIEW', 'MANAGE'],
                // Routes the system admin to the platform governance dashboard (D0).
                'system_dashboard' => ['VIEW', 'MANAGE'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW', 'MANAGE'],
            ],
        ];

        DB::table('screen_permissions')->delete();

        foreach ($grants as $roleCode => $screenMap) {
            $role = Role::query()->where('code', $roleCode)->first();
            if (! $role) {
                continue;
            }

            foreach ($screenMap as $screenKey => $capabilities) {
                $screen = Screen::query()->where('key', $screenKey)->first();
                if (! $screen) {
                    continue;
                }

                foreach ($capabilities as $capability) {
                    DB::table('screen_permissions')->insertOrIgnore([
                        'role_id' => $role->id,
                        'screen_id' => $screen->id,
                        'capability' => $capability,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}

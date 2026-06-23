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
            'merchants' => 'المستوردون',
            'workflow_designer' => 'مصمم سير العمل',
            'requests' => 'الطلبات',
            'reports' => 'التقارير',
            'audit' => 'سجل التدقيق',
            'reference_data' => 'البيانات المرجعية',
            'screen_permissions' => 'صلاحيات الشاشات',
            'notifications' => 'الإشعارات',
            'settings' => 'الإعدادات',
        ];

        foreach ($screens as $key => $label) {
            Screen::query()->updateOrCreate(['key' => $key], ['label' => $label]);
        }

        // Map governance role codes → screen capabilities.
        // Format: role_code => [ screen_key => [capabilities] ]
        $grants = [
            // ── Commercial Banks ──────────────────────────────────
            'intake' => [
                'requests' => ['VIEW', 'CREATE'],
                'merchants' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'internal_reviewer' => [
                'requests' => ['VIEW', 'UPDATE'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'bank_admin' => [
                'requests' => ['VIEW', 'CREATE'],
                'merchants' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'users' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'reports' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'fx_swift' => [
                'requests' => ['VIEW', 'UPDATE'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],

            // ── National Committee ────────────────────────────────
            'support' => [
                'requests' => ['VIEW', 'UPDATE'],
                'audit' => ['VIEW'],
                'reports' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'committee_manager' => [
                'requests' => ['VIEW', 'UPDATE'],
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],
            'fx_confirm' => [
                'requests' => ['VIEW', 'UPDATE'],
                'reports' => ['VIEW'],
                'audit' => ['VIEW'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW'],
            ],

            // ── System Administration ─────────────────────────────
            'system_admin' => [
                'organizations' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'teams' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'roles' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'banks' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'users' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'merchants' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'workflow_designer' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'requests' => ['VIEW'],
                'reports' => ['VIEW', 'EXPORT'],
                'audit' => ['VIEW', 'EXPORT'],
                'reference_data' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'screen_permissions' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'MANAGE'],
                'notifications' => ['VIEW'],
                'settings' => ['VIEW', 'UPDATE', 'MANAGE'],
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

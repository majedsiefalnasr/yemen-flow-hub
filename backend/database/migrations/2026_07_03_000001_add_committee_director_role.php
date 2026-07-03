<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $organizationId = DB::table('organizations')->where('code', 'national_committee')->value('id');
        $managerRoleId = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('code', 'committee_manager')
            ->value('id');

        $directorRoleId = DB::table('roles')->insertGetId([
            'organization_id' => $organizationId,
            'code' => 'committee_director',
            'name' => 'مدير اللجنة التنفيذية (مدير)',
            'is_system' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Repoint COMMITTEE_DIRECTOR users' pivot from committee_manager to committee_director.
        $directorUserIds = DB::table('users')->where('role', 'COMMITTEE_DIRECTOR')->pluck('id');

        foreach ($directorUserIds as $userId) {
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role_id', $managerRoleId)
                ->delete();

            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $userId,
                'role_id' => $directorRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Copy committee_manager's screen_permissions as director's starting grant set
        // (Task 1 seeder update will refine this; this keeps the migration idempotent
        // even if seeders haven't run yet in this environment).
        $managerPermissions = DB::table('screen_permissions')->where('role_id', $managerRoleId)->get();
        foreach ($managerPermissions as $permission) {
            DB::table('screen_permissions')->insertOrIgnore([
                'role_id' => $directorRoleId,
                'screen_id' => $permission->screen_id,
                'capability' => $permission->capability,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $organizationId = DB::table('organizations')->where('code', 'national_committee')->value('id');
        $directorRoleId = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('code', 'committee_director')
            ->value('id');

        if ($directorRoleId === null) {
            return;
        }

        DB::table('screen_permissions')->where('role_id', $directorRoleId)->delete();
        DB::table('user_roles')->where('role_id', $directorRoleId)->delete();
        DB::table('roles')->where('id', $directorRoleId)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyRows = DB::table('screen_permissions')
                ->whereIn('capability', ['CREATE', 'UPDATE', 'DELETE'])
                ->orderBy('id')
                ->get(['id', 'role_id', 'screen_id']);

            // Track which (role_id, screen_id) pairs already resolve to MANAGE
            // -- either because a MANAGE row pre-existed, or because an earlier
            // legacy row in this same loop was just rewritten to MANAGE. Any
            // further legacy row for that pair is redundant and gets deleted
            // instead of rewritten, so the unique constraint on
            // (role_id, screen_id, capability) is never violated.
            $manageResolvedPairs = DB::table('screen_permissions')
                ->where('capability', 'MANAGE')
                ->get(['role_id', 'screen_id'])
                ->map(fn ($row) => "{$row->role_id}:{$row->screen_id}")
                ->flip();

            foreach ($legacyRows as $row) {
                $pairKey = "{$row->role_id}:{$row->screen_id}";

                if ($manageResolvedPairs->has($pairKey)) {
                    DB::table('screen_permissions')->where('id', $row->id)->delete();

                    continue;
                }

                DB::table('screen_permissions')->where('id', $row->id)->update([
                    'capability' => 'MANAGE',
                    'updated_at' => now(),
                ]);

                $manageResolvedPairs->put($pairKey, true);
            }

            // system_admin never holds MANAGE on the merchants screen.
            $systemAdminRoleId = DB::table('roles')->where('code', 'system_admin')->value('id');
            $merchantsScreenId = DB::table('screens')->where('key', 'merchants')->value('id');

            if ($systemAdminRoleId !== null && $merchantsScreenId !== null) {
                DB::table('screen_permissions')
                    ->where('role_id', $systemAdminRoleId)
                    ->where('screen_id', $merchantsScreenId)
                    ->where('capability', 'MANAGE')
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // Lossy forward migration (CREATE/UPDATE/DELETE -> MANAGE is not
        // reversible) -- intentionally no-op, matching the design doc's
        // documented rollback strategy (revert the code deploy, leave data
        // migration applied).
    }
};

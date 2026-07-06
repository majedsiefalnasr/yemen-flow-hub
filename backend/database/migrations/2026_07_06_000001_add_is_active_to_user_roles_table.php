<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role_id');
        });

        // Backfill: exactly one active role per user — keep the newest assignment.
        $duplicateUserIds = DB::table('user_roles')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicateUserIds as $userId) {
            $activeId = DB::table('user_roles')
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('id', '!=', $activeId)
                ->update(['is_active' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};

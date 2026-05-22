<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('user_agent', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->select(['id', 'user_agent'])
            ->whereNotNull('user_agent')
            ->orderBy('id')
            ->chunkById(100, function ($logs): void {
                foreach ($logs as $log) {
                    if (mb_strlen($log->user_agent) <= 255) {
                        continue;
                    }

                    DB::table('audit_logs')
                        ->where('id', $log->id)
                        ->update(['user_agent' => mb_substr($log->user_agent, 0, 255)]);
                }
            });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('user_agent', 255)->nullable()->change();
        });
    }
};

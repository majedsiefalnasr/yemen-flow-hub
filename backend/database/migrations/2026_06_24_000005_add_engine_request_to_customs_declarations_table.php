<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow a declaration to be tied to EITHER a legacy import_requests row OR an
        // engine_requests row. Legacy rows keep request_id; engine rows use the new
        // engine_request_id. request_id becomes nullable (additive; legacy unchanged).
        Schema::table('customs_declarations', function (Blueprint $table) {
            $table->foreignId('request_id')->nullable()->change();
            $table->foreignId('engine_request_id')
                ->nullable()
                ->after('request_id')
                ->unique()
                ->constrained('engine_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customs_declarations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('engine_request_id');
        });

        // Restore NOT NULL only when no engine-linked rows remain (SQLite tolerates the
        // change(); MySQL would reject NULLs present).
        if (DB::table('customs_declarations')->whereNull('request_id')->doesntExist()) {
            Schema::table('customs_declarations', function (Blueprint $table) {
                $table->foreignId('request_id')->nullable(false)->change();
            });
        }
    }
};

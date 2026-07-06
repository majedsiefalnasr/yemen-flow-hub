<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->string('invoice_number_normalized', 100)->nullable()->after('invoice_number')->index();
        });

        // Backfill: apply trim + uppercase + collapse-spaces normalization to existing rows.
        // REGEXP_REPLACE requires MySQL 8.0+ / MariaDB 10.0+, which is the project minimum.
        DB::statement(
            "UPDATE engine_requests
             SET invoice_number_normalized = TRIM(REGEXP_REPLACE(UPPER(invoice_number), '\\\\s+', ' '))
             WHERE invoice_number IS NOT NULL AND invoice_number != ''"
        );
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropIndex(['invoice_number_normalized']);
            $table->dropColumn('invoice_number_normalized');
        });
    }
};

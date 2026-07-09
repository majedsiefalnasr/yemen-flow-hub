<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-003 (perf audit): covering index for entity + date-range audit-log
 * filters, gated on the API-007 code fix (exact subject_type match + half-open
 * date range replacing whereDate()/infix LIKE). Without that code fix this
 * index goes unused — DATE(created_at) and '%X%' cannot seek an index.
 *
 * Existing (subject_type, subject_id) index doesn't help created_at ordering;
 * this composite lets an entity+date-range query use a covering range scan
 * instead of the reverse-PRIMARY early-stop fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['subject_type', 'created_at'], 'al_subject_created');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('al_subject_created');
        });
    }
};

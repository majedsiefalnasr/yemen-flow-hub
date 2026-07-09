<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-001 (perf audit): covering index for the SLA correlated subquery.
 *
 * The my-queue / stats / reports SLA path resolves each request's stage-entry
 * time via `MAX(created_at) WHERE request_id = ? AND to_stage_id = ?`. The
 * existing (request_id, created_at) index forces a post-lookup filter on
 * to_stage_id; this index makes the subquery a covering lookup.
 *
 * Measured at the design-target dataset (1M requests / 5M history rows):
 * my-queue ~2.6s -> ~0.7s; subquery cost 4.31 -> 1.14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_history', function (Blueprint $table): void {
            $table->index(['request_id', 'to_stage_id', 'created_at'], 'wh_req_tostage_created');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_history', function (Blueprint $table): void {
            $table->dropIndex('wh_req_tostage_created');
        });
    }
};

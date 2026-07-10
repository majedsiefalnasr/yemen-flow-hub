<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-001/DB-002 UNION-restructure follow-up: the 200K-row load harness
 * (perf:load-scenario, post-UnionStagePaginator) still failed the p95 <=
 * 300ms gate for both my-queue (462ms) and the list endpoint (586ms).
 * EXPLAIN on the actual per-branch query traced the cause to the composite
 * indexes only covering the LEADING sort column, not the full multi-column
 * ORDER BY UnionStagePaginator's branches issue:
 *
 * - my-queue's branches sort by
 *   `sla_deadline_epoch ASC, stage_entered_at ASC, id ASC` (plus a leading
 *   CASE WHEN tiebreaker that is a no-op when every row's stage has an SLA
 *   configured, as in the load harness's fixture). `er_stage_sla_deadline`
 *   (current_stage_id, sla_deadline_epoch) only covers the first sort
 *   column -- MySQL uses it for `ORDER BY sla_deadline_epoch` alone
 *   (confirmed via EXPLAIN: `Using index`, no filesort), but the moment a
 *   tie on sla_deadline_epoch needs stage_entered_at/id to break it (common
 *   at scale -- many rows in the same stage share a deadline), MySQL falls
 *   back to `Using where; Using filesort` for the tied groups. Widening the
 *   index to include the tiebreakers (all ASC, matching the query's own
 *   ASC/ASC/ASC order) closes this with a plain composite index -- no
 *   direction mismatch here.
 * - The list endpoint's branches sort by `created_at DESC, id ASC`, scoped
 *   by a single-value `current_stage_id = X` (not `bank_id`, since the
 *   UNION restructure's branch-factory scopes per stage). No existing
 *   index covers `(current_stage_id, created_at, id)` at all --
 *   `er_bank_created` is keyed by `bank_id`, and the query no longer scans
 *   it the same way, per EXPLAIN. Unlike the my-queue index, this sort
 *   MIXES directions (created_at DESC, id ASC) -- confirmed via EXPLAIN
 *   that a plain ascending composite index still filesorts even when
 *   forced (`USE INDEX`), because a single ascending B-tree cannot satisfy
 *   both directions in one scan. `id ASC` here is the tiebreaker
 *   EngineRequestController::index() has used since before this branch
 *   (verified via git history) -- changing it to `id DESC` to make both
 *   directions match would be an observable pagination-order change for
 *   same-`created_at` rows, which the byte-identical-response constraint
 *   this plan operates under rules out. Instead the index itself is
 *   declared with an explicit per-column direction
 *   (`created_at DESC, id ASC`), a MySQL 8.0+ feature Laravel's Blueprint
 *   fluent API has no per-column direction parameter for, hence the raw
 *   DDL below.
 *
 * This is a genuine, fixable index gap -- not the fundamental MySQL
 * IN-list-vs-ORDER-BY conflict the UNION restructure already solved (that
 * conflict is specifically about a *multi-value* IN filter; these
 * per-branch queries filter on a single equality value, so a covering
 * index closes the sort cleanly). Widens/adds two composite indexes that
 * cover the full sort key each endpoint's per-branch query actually uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropIndex('er_stage_sla_deadline');
            $table->index(
                ['current_stage_id', 'sla_deadline_epoch', 'stage_entered_at', 'id'],
                'er_stage_sla_deadline'
            );
        });

        // Per-column index direction (DESC on created_at, ASC on id) has no
        // Blueprint fluent-API equivalent -- raw DDL is the only way to
        // declare it. MySQL-only (matches this whole restructure's scope;
        // the load harness itself refuses to run on any connection but
        // mysql), guarded the same way for safety.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'CREATE INDEX er_stage_created ON engine_requests (current_stage_id, created_at DESC, id ASC)'
            );
        } else {
            Schema::table('engine_requests', function (Blueprint $table) {
                $table->index(['current_stage_id', 'created_at', 'id'], 'er_stage_created');
            });
        }
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropIndex('er_stage_created');

            $table->dropIndex('er_stage_sla_deadline');
            $table->index(['current_stage_id', 'sla_deadline_epoch'], 'er_stage_sla_deadline');
        });
    }
};

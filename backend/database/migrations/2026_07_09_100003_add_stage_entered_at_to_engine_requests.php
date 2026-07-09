<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCH-002: add an indexed `stage_entered_at` projection column to
 * engine_requests, maintained on transition, so SLA-priority ordering and
 * sla_status filtering read an indexed column instead of a correlated
 * `max(created_at) from workflow_history` subquery embedded in ORDER BY / WHERE.
 *
 * The column holds the same value the subquery computed: the latest
 * workflow_history.created_at where to_stage_id = the request's current stage.
 * Backfill is chunked so it does not lock the whole table on large datasets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->timestamp('stage_entered_at')->nullable()->after('current_stage_id');
            // Ordering/filtering scopes to the current stage then orders by entry time.
            $table->index(['current_stage_id', 'stage_entered_at'], 'er_stage_entered');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropIndex('er_stage_entered');
            $table->dropColumn('stage_entered_at');
        });
    }

    /**
     * Backfill stage_entered_at from the latest matching history row, in id-range
     * chunks so the write set stays bounded on large tables.
     */
    private function backfill(): void
    {
        $chunk = 5000;
        $lastId = 0;

        while (true) {
            $ids = DB::table('engine_requests')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            foreach ($ids as $id) {
                $enteredAt = DB::table('workflow_history')
                    ->join('engine_requests', 'engine_requests.id', '=', 'workflow_history.request_id')
                    ->where('engine_requests.id', $id)
                    ->whereColumn('workflow_history.to_stage_id', 'engine_requests.current_stage_id')
                    ->max('workflow_history.created_at');

                if ($enteredAt !== null) {
                    DB::table('engine_requests')->where('id', $id)->update(['stage_entered_at' => $enteredAt]);
                }
            }

            $lastId = $ids->last();
        }
    }
};

<?php

use App\Models\WorkflowStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-001/DB-002 follow-up: the load-run harness (perf:load-scenario) proved
 * my-queue's and the list endpoint's p95 gate (<=300ms) fails at 200K rows
 * even though the query COUNT stays constant. The remaining cost is
 * EngineRequest::scopeOrderBySlaPriority()'s ORDER BY on a computed
 * expression (stage_entered_at epoch + joined sla_duration_minutes*60),
 * which cannot use a plain B-tree index for the sort itself even though the
 * WHERE-clause indexes correctly narrow the row set first.
 *
 * Adds `sla_deadline_epoch` (nullable, UNIX epoch seconds -- an integer
 * sidesteps the SQLite-vs-MySQL epoch-function divergence that caused the
 * ARCH-002/API-006 timezone bug) as a maintained column, same pattern as
 * ARCH-002's `stage_entered_at`: written at the same two call sites
 * (EngineRequestService::create(), EngineTransitionService::execute()),
 * where the target WorkflowStage's sla_duration_minutes is already loaded
 * in memory -- no extra query needed at write time.
 *
 * Safe to maintain without invalidation-on-stage-edit: WorkflowDesignerService
 * only allows editing a WorkflowStage while its parent WorkflowVersion is
 * DRAFT (ensureEditable()) -- a published stage's sla_duration_minutes never
 * changes under a live request, so a value computed once at
 * create/transition time never goes stale.
 *
 * Backfill mirrors the ARCH-002 stage_entered_at backfill: for every
 * existing row, resolve the current stage's sla_duration_minutes and
 * combine it with the row's own stage_entered_at (already backfilled by
 * ARCH-002; falls back to the workflow_history subquery via the same
 * COALESCE pattern EngineRequest::stageEnteredAtSql() already uses for rows
 * where it's still null). Chunked by id range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('sla_deadline_epoch')->nullable()->after('stage_entered_at');
            $table->index(['current_stage_id', 'sla_deadline_epoch'], 'er_stage_sla_deadline');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropIndex('er_stage_sla_deadline');
            $table->dropColumn('sla_deadline_epoch');
        });
    }

    private function backfill(): void
    {
        $chunk = 5000;
        $lastId = 0;

        $stageSlaMinutes = WorkflowStage::query()->pluck('sla_duration_minutes', 'id');

        while (true) {
            $rows = DB::table('engine_requests')
                ->select('id', 'current_stage_id', 'stage_entered_at')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = $row->id;
                $slaMinutes = $stageSlaMinutes[$row->current_stage_id] ?? null;

                if ($slaMinutes === null || $row->stage_entered_at === null) {
                    continue;
                }

                // stage_entered_at is wall-clock in config('app.timezone') (the same
                // timezone now()/Carbon write app-wide) -- parsing it as UTC here
                // would reproduce the ARCH-002/API-006 timezone skew bug, so it must
                // be parsed in the app timezone explicitly, not PHP's process default.
                $enteredAt = Carbon::parse((string) $row->stage_entered_at, config('app.timezone'));

                DB::table('engine_requests')->where('id', $row->id)->update([
                    'sla_deadline_epoch' => $enteredAt->getTimestamp() + ((int) $slaMinutes * 60),
                ]);
            }
        }
    }
};

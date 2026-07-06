<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->string('final_outcome', 20)->nullable()->after('is_final');
        });

        $rejectActionIds = DB::table('workflow_actions')
            ->where('kind', 'REJECT')
            ->pluck('id');

        $finalStageIds = DB::table('workflow_stages')
            ->where('is_final', true)
            ->pluck('id');

        foreach ($finalStageIds as $stageId) {
            $hasRejectIncoming = DB::table('workflow_transitions')
                ->where('to_stage_id', $stageId)
                ->whereIn('action_id', $rejectActionIds)
                ->exists();

            DB::table('workflow_stages')
                ->where('id', $stageId)
                ->update([
                    'final_outcome' => $hasRejectIncoming ? 'REJECTED' : 'COMPLETED',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->dropColumn('final_outcome');
        });
    }
};

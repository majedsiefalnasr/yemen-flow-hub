<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_transitions', function (Blueprint $table): void {
            $table->boolean('is_default_submit')->default(false)->after('confirmation_message');
            $table->boolean('is_self_loop')->default(false)->after('is_default_submit');
            $table->string('transition_type', 20)->default('FORWARD')->after('is_self_loop');
            $table->boolean('is_destructive')->default(false)->after('transition_type');
        });

        Schema::table('workflow_definitions', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::table('workflow_transitions')
            ->whereColumn('from_stage_id', 'to_stage_id')
            ->update(['is_self_loop' => true]);

        $transitions = DB::table('workflow_transitions')
            ->join('workflow_actions', 'workflow_actions.id', '=', 'workflow_transitions.action_id')
            ->select('workflow_transitions.id', 'workflow_actions.kind')
            ->get();

        foreach ($transitions as $row) {
            $type = match ($row->kind) {
                'REJECT' => 'REJECT',
                'CLOSE' => 'CLOSE',
                default => 'FORWARD',
            };
            DB::table('workflow_transitions')->where('id', $row->id)->update(['transition_type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('workflow_transitions', function (Blueprint $table): void {
            $table->dropColumn(['is_default_submit', 'is_self_loop', 'transition_type', 'is_destructive']);
        });

        Schema::table('workflow_definitions', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};

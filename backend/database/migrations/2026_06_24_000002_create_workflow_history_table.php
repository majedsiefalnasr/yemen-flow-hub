<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('engine_requests')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('workflow_stages');
            $table->foreignId('to_stage_id')->constrained('workflow_stages');
            $table->string('action_code', 50)->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->text('comments')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_history');
    }
};

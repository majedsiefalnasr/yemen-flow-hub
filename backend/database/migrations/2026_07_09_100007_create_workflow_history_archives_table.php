<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCH-006: workflow_history grows one row per transition and had no
 * retention/archival path — unlike audit_logs (SEC-002 + the pre-existing
 * AuditLogArchiveService). Mirrors that same archive-table + batch-move
 * pattern: rows move here and are deleted from the hot table once the owning
 * engine_request is no longer ACTIVE and past the retention horizon (never
 * archives history for an in-flight request — EngineRequest::withStageEntry()
 * and ReportController::stageDuration() both depend on a request's own
 * workflow_history rows being present while it is active).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_history_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreignId('from_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('workflow_stages');
            $table->string('action_code', 50)->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->text('comments')->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->unique('source_id');
            $table->index(['request_id', 'created_at']);
            $table->index(['bank_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_history_archives');
    }
};

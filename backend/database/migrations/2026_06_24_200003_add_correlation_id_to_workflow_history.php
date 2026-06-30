<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_history', function (Blueprint $table): void {
            // Links a workflow_history row to its audit_logs entry for the same transition
            // (FR-AUD2). Written from the same UUID the transition passes to the audit log.
            $table->uuid('correlation_id')->nullable()->after('comments');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_history', function (Blueprint $table): void {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('actor_role_id')->nullable()->after('user_role')
                ->constrained('roles')->nullOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->after('subject_id')
                ->constrained('engine_requests')->nullOnDelete();
            $table->string('correlation_id', 36)->nullable()->after('workflow_instance_id');
            $table->json('old_values')->nullable()->after('metadata');
            $table->json('new_values')->nullable()->after('old_values');

            $table->index(['user_id', 'created_at'], 'audit_logs_actor_time');
            $table->index('correlation_id');
            $table->index('workflow_instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_actor_time');
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['workflow_instance_id']);
            $table->dropConstrainedForeignId('actor_role_id');
            $table->dropConstrainedForeignId('workflow_instance_id');
            $table->dropColumn(['correlation_id', 'old_values', 'new_values']);
        });
    }
};

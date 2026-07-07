<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_role')->nullable();
            $table->foreignId('actor_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('engine_requests')->nullOnDelete();
            $table->string('correlation_id', 36)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->unique('source_id');
            $table->index(['action', 'archived_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_archives');
    }
};

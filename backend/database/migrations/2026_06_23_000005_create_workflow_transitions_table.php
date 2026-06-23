<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('workflow_actions')->cascadeOnDelete();
            $table->foreignId('to_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->boolean('requires_comment')->default(false);
            $table->string('confirmation_message')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['from_stage_id', 'action_id']);
            $table->index('workflow_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};

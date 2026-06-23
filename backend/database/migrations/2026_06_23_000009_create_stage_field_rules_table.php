<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_field_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('field_definitions')->cascadeOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['stage_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_field_rules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('state')->default('DRAFT'); // DRAFT | PUBLISHED | ARCHIVED
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'version_number']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};

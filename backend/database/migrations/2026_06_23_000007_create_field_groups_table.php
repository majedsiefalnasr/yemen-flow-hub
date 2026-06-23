<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['workflow_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_groups');
    }
};

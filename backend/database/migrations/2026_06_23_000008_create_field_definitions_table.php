<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->foreignId('field_group_id')->constrained('field_groups')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type'); // TEXT|NUMBER|DATE|SELECT|DYNAMIC_SELECT|TEXTAREA|FILE|CURRENCY|CHECKBOX
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();
            $table->string('default_value')->nullable();
            $table->decimal('min_value', 20, 4)->nullable();
            $table->decimal('max_value', 20, 4)->nullable();
            $table->unsignedInteger('min_length')->nullable();
            $table->unsignedInteger('max_length')->nullable();
            $table->string('regex_pattern')->nullable();
            $table->json('options')->nullable();
            $table->foreignId('reference_table_id')->nullable()->constrained('reference_tables')->nullOnDelete();
            $table->string('dynamic_source')->nullable(); // MERCHANTS|MERCHANT_COMPANIES|REFERENCE_DATA
            $table->json('allowed_file_types')->nullable();
            $table->unsignedInteger('max_file_size')->nullable();
            $table->boolean('multiple')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['workflow_version_id', 'key']);
            $table->index(['field_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_definitions');
    }
};

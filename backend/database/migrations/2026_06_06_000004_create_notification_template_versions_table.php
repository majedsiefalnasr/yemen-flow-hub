<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_template_id')
                ->constrained('notification_templates')
                ->cascadeOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active_version')->default(false);
            $table->timestamps();

            $table->index(['notification_template_id', 'is_active_version'], 'ntv_template_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_template_versions');
    }
};

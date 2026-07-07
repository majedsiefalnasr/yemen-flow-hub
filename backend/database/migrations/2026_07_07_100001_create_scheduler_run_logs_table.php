<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 100);
            $table->string('status', 20);
            $table->unsignedInteger('affected_count')->default(0);
            $table->json('meta')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['command', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_run_logs');
    }
};

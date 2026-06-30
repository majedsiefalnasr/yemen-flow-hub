<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('report_type', 50);
            $table->json('filters')->nullable();
            $table->string('format', 10)->default('csv');
            $table->string('status', 20)->default('PENDING');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};

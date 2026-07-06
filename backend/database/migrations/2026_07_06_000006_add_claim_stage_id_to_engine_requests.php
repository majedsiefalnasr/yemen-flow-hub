<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->foreignId('claim_stage_id')
                ->nullable()
                ->after('claim_expires_at')
                ->constrained('workflow_stages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claim_stage_id');
        });
    }
};

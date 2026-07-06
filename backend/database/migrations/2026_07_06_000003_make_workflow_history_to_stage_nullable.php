<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_history', function (Blueprint $table): void {
            $table->dropForeign(['to_stage_id']);
            $table->unsignedBigInteger('to_stage_id')->nullable()->change();
            $table->foreign('to_stage_id')->references('id')->on('workflow_stages');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_history', function (Blueprint $table): void {
            $table->dropForeign(['to_stage_id']);
            $table->unsignedBigInteger('to_stage_id')->nullable(false)->change();
            $table->foreign('to_stage_id')->references('id')->on('workflow_stages');
        });
    }
};

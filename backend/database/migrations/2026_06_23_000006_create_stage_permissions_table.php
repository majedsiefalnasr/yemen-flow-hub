<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('access_level'); // VIEW | EXECUTE
            $table->string('display_label');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index('stage_id');
            $table->index(['organization_id', 'team_id', 'role_id', 'user_id'], 'stage_permissions_match_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_permissions');
    }
};

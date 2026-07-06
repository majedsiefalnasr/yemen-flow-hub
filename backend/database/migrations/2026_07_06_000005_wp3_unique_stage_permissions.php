<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_permissions', function (Blueprint $table): void {
            $table->unique(
                ['stage_id', 'organization_id', 'team_id', 'role_id', 'user_id', 'access_level'],
                'stage_permissions_audience_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stage_permissions', function (Blueprint $table): void {
            $table->dropUnique('stage_permissions_audience_unique');
        });
    }
};

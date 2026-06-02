<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colour picker was retired; every avatar now renders with the shared
 * brand palette defined on the frontend. Only the variant choice is user-
 * controllable, so the per-user palette column is no longer needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar_colors')) {
                $table->dropColumn('avatar_colors');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('avatar_colors')->nullable()->after('avatar_variant');
        });
    }
};

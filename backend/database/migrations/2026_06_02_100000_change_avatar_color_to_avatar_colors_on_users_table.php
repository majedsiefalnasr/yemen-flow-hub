<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the single-colour avatar_color column with a multi-colour
 * avatar_colors JSON column so users can pick up to 4 palette anchors.
 * The frontend pads the chosen colours back to the 5-stop palette
 * boring-avatars expects, using the shared brand colours as filler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_color');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('avatar_colors')->nullable()->after('avatar_variant');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_colors');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_color', 9)->nullable()->after('avatar_variant');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add nullable first so the ALTER succeeds on any populated table
        Schema::table('banks', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('id');
            $table->string('name_en')->nullable()->after('name_ar');
        });

        // Backfill from existing 'name' column before dropping it
        DB::statement('UPDATE banks SET name_ar = name, name_en = name WHERE name_ar IS NULL OR name_ar = ""');

        // Make non-nullable now that all rows have values
        Schema::table('banks', function (Blueprint $table) {
            $table->string('name_ar')->nullable(false)->change();
            $table->string('name_en')->nullable(false)->change();
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->dropUnique('banks_name_unique');
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        // Restore name from name_ar on rollback
        DB::statement('UPDATE banks SET name = name_ar WHERE name IS NULL OR name = ""');

        Schema::table('banks', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_en']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::statement('UPDATE banks SET name = name_ar WHERE name IS NULL OR name = ""');

        Schema::table('banks', function (Blueprint $table) {
            $table->string('name')->nullable(false)->unique()->change();
            $table->dropColumn(['name_ar', 'name_en']);
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('id');
            $table->string('name_en')->nullable()->after('name_ar');
        });

        DB::statement('UPDATE banks SET name_ar = name, name_en = name WHERE name_ar IS NULL OR name_ar = ""');

        Schema::table('banks', function (Blueprint $table) {
            $table->string('name_ar')->nullable(false)->change();
            $table->string('name_en')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }
};

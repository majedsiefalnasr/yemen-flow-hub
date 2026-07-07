<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->unsignedInteger('total_matching')->nullable()->after('file_path');
            $table->unsignedInteger('exported_count')->nullable()->after('total_matching');
            $table->boolean('truncated')->default(false)->after('exported_count');
            $table->text('truncation_note')->nullable()->after('truncated');
        });
    }

    public function down(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->dropColumn(['total_matching', 'exported_count', 'truncated', 'truncation_note']);
        });
    }
};

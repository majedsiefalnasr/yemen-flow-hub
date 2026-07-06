<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_request_documents', function (Blueprint $table) {
            $table->string('scan_status', 20)
                ->default('clean')
                ->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('engine_request_documents', function (Blueprint $table) {
            $table->dropColumn('scan_status');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_request_documents', function (Blueprint $table) {
            $table->string('status', 20)
                ->default('active')
                ->after('version');
            $table->foreignId('superseded_by')
                ->nullable()
                ->after('status')
                ->constrained('engine_request_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('engine_request_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by');
            $table->dropColumn('status');
        });
    }
};

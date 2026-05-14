<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->foreignId('merchant_id')
                ->nullable()
                ->after('bank_id')
                ->constrained('merchants')
                ->nullOnDelete();
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merchant_id');
        });
    }
};


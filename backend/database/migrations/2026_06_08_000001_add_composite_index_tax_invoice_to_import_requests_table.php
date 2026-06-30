<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->index(
                ['trader_snapshot_tax_number', 'invoice_number'],
                'idx_trader_snapshot_tax_invoice'
            );
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropIndex('idx_trader_snapshot_tax_invoice');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the composite index that spans a trader_snapshot column before
        // removing that column; MySQL cannot drop a column that is part of an index.
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropIndex('idx_trader_snapshot_tax_invoice');
        });

        // Drop the FK constraint pointing at traders.id, then remove the
        // trader_id column and all denormalised trader_snapshot_* columns.
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropForeign(['trader_id']);
            $table->dropColumn([
                'trader_id',
                'trader_snapshot_name',
                'trader_snapshot_tax_number',
                'trader_snapshot_tax_card_expiry',
                'trader_snapshot_commercial_registration_number',
                'trader_snapshot_commercial_registration_expiry',
            ]);
        });

        // Drop trader tables in FK dependency order (children before parent).
        Schema::dropIfExists('trader_owners');
        Schema::dropIfExists('trader_companies');
        Schema::dropIfExists('traders');
    }

    public function down(): void
    {
        Schema::create('traders', function (Blueprint $table): void {
            $table->id();
            $table->string('tax_number')->unique();
            $table->string('trader_name')->nullable();
            $table->date('tax_card_expiry')->nullable();
            $table->string('commercial_registration_number')->nullable();
            $table->date('commercial_registration_expiry')->nullable();
            $table->timestamps();
        });

        Schema::create('trader_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trader_id')->constrained('traders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->index('trader_id');
        });

        Schema::create('trader_owners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trader_id')->constrained('traders')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->index('trader_id');
        });

        Schema::table('import_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('trader_id')->nullable()->after('merchant_id');
            $table->string('trader_snapshot_name')->nullable();
            $table->string('trader_snapshot_tax_number')->nullable();
            $table->date('trader_snapshot_tax_card_expiry')->nullable();
            $table->string('trader_snapshot_commercial_registration_number')->nullable();
            $table->date('trader_snapshot_commercial_registration_expiry')->nullable();

            $table->foreign('trader_id')->references('id')->on('traders')->nullOnDelete();
            $table->index(
                ['trader_snapshot_tax_number', 'invoice_number'],
                'idx_trader_snapshot_tax_invoice'
            );
        });
    }
};

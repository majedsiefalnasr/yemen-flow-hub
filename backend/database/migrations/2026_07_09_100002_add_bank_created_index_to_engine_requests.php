<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-002 (perf audit): composite index for the default bank-scoped list sort.
 *
 * `GET /v1/engine-requests` filters by bank_id and orders by created_at DESC.
 * Without a composite, MySQL filters the whole bank partition and sorts it.
 * This index turns it into a covering range scan and pairs with the
 * ARCH-004 code change that replaces whereDate() with half-open range bounds
 * (a function-wrapped created_at cannot use this index).
 *
 * Measured at the design-target dataset: list search/date ~0.3s -> ~0.08s.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table): void {
            $table->index(['bank_id', 'created_at', 'id'], 'er_bank_created');
        });
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table): void {
            $table->dropIndex('er_bank_created');
        });
    }
};

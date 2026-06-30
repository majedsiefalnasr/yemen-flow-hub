<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            // Hybrid projection (DI-2) for the financing ledger: the only indexed
            // column the named-lock → row-lock → sum protocol needs that has no other
            // queryable source on the engine row. Mirrors import_requests.request_percentage.
            $table->decimal('request_percentage', 5, 2)->nullable()->after('invoice_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('engine_requests', function (Blueprint $table) {
            $table->dropIndex(['request_percentage']);
            $table->dropColumn('request_percentage');
        });
    }
};

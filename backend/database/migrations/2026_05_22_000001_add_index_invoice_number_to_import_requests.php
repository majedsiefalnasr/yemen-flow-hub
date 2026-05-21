<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table) {
            $table->index(['invoice_number', 'deleted_at'], 'idx_invoice_number_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_number_deleted_at');
        });
    }
};

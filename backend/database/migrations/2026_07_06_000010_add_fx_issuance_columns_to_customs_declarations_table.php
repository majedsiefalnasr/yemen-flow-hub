<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make issued_by nullable — official issuer identity is deferred (F-14).
        // The transition actor is now recorded in generated_by, not issued_by.
        Schema::table('customs_declarations', function (Blueprint $table): void {
            $table->foreignId('issued_by')->nullable()->change();

            $table->foreignId('generated_by')
                ->nullable()
                ->after('issued_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('signed_uploaded_by')
                ->nullable()
                ->after('signed_fx_doc_uploaded_by')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Backfill generated_by = issued_by for existing rows so nothing breaks.
        DB::table('customs_declarations')
            ->whereNull('generated_by')
            ->whereNotNull('issued_by')
            ->update(['generated_by' => DB::raw('issued_by')]);
    }

    public function down(): void
    {
        Schema::table('customs_declarations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signed_uploaded_by');
            $table->dropConstrainedForeignId('generated_by');
            $table->foreignId('issued_by')->nullable(false)->change();
        });
    }
};

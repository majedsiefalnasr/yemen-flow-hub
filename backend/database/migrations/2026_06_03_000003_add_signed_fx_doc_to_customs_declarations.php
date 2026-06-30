<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customs_declarations', function (Blueprint $table): void {
            $table->string('signed_fx_doc_path')->nullable()->after('pdf_path');
            $table->timestamp('signed_fx_doc_uploaded_at')->nullable()->after('signed_fx_doc_path');
            $table->foreignId('signed_fx_doc_uploaded_by')
                ->nullable()
                ->after('signed_fx_doc_uploaded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customs_declarations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signed_fx_doc_uploaded_by');
            $table->dropColumn(['signed_fx_doc_uploaded_at', 'signed_fx_doc_path']);
        });
    }
};

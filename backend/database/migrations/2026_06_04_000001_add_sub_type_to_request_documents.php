<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            // Identifies the wizard slot a REQUEST_DOC belongs to:
            // proforma_invoice | commercial_register | tax_card | confirmation_request | extra
            $table->string('document_sub_type', 50)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->dropColumn('document_sub_type');
        });
    }
};

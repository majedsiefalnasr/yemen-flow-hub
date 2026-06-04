<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->foreignId('document_type_id')->nullable()->after('type')->constrained('document_types')->nullOnDelete();
            $table->index('document_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropIndex(['document_type_id']);
            $table->dropColumn('document_type_id');
        });
    }
};

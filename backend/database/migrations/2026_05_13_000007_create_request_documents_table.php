<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->index('request_id', 'rd_request_id_idx');
            $table->unsignedBigInteger('uploaded_by');
            $table->index('uploaded_by', 'rd_uploaded_by_idx');
            $table->enum('type', ['REQUEST_DOC', 'SWIFT', 'CUSTOMS']);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->foreign('request_id', 'rd_request_id_fk')
                ->references('id')
                ->on('import_requests')
                ->cascadeOnDelete();

            $table->foreign('uploaded_by', 'rd_uploaded_by_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_documents');
    }
};

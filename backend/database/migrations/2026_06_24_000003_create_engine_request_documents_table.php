<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('engine_requests')->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained('field_definitions');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('stage_id')->constrained('workflow_stages');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 50);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['request_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_request_documents');
    }
};

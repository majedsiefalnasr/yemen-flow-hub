<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_request_reference_sequences', function (Blueprint $table) {
            $table->string('year', 4)->primary();
            $table->unsignedInteger('last_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_request_reference_sequences');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trader_owners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trader_id')->constrained('traders')->cascadeOnDelete();
            $table->string('full_name');
            $table->decimal('ownership_percentage', 5, 2);
            $table->string('nationality')->nullable();
            $table->string('identification_number')->nullable();
            $table->timestamps();

            $table->index('trader_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trader_owners');
    }
};

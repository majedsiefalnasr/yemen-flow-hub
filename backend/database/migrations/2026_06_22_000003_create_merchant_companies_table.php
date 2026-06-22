<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('commercial_registration_number')->unique();
            $table->date('commercial_registration_expiry')->nullable();
            $table->unsignedBigInteger('sector_reference_value_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_companies');
    }
};

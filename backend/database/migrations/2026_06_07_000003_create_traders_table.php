<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traders', function (Blueprint $table): void {
            $table->id();
            $table->string('tax_number')->unique();
            $table->string('trader_name');
            $table->date('tax_card_expiry');
            $table->string('commercial_registration_number');
            $table->date('commercial_registration_expiry');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traders');
    }
};

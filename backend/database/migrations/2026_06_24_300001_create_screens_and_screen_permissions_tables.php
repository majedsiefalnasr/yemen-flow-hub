<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('screen_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('screens')->cascadeOnDelete();
            $table->string('capability');
            $table->timestamps();
            $table->unique(['role_id', 'screen_id', 'capability']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_permissions');
        Schema::dropIfExists('screens');
    }
};

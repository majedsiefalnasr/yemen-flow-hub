<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted TOTP secret — set when user completes authenticator app setup
            $table->string('totp_secret')->nullable()->after('mfa_enabled');
            // Tracks whether the user has completed TOTP setup (distinct from mfa_enabled)
            $table->boolean('totp_enabled')->default(false)->after('totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_enabled']);
        });
    }
};

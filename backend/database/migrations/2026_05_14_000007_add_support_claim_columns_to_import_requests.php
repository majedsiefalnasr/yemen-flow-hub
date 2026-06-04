<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->foreignId('claimed_by')
                ->nullable()
                ->after('current_owner_role')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            $table->timestamp('claim_expires_at')->nullable()->after('claimed_at');
            $table->index('claimed_by');
            $table->index('claim_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('claimed_by');
            $table->dropColumn(['claimed_at', 'claim_expires_at']);
        });
    }
};

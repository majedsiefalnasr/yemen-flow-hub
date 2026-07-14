<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('upload_session_token', 64);
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
            $table->foreignId('bank_id')->nullable()->constrained('banks');
            $table->foreignId('workflow_version_id')->constrained('workflow_versions');
            $table->foreignId('field_id')->nullable()->constrained('field_definitions');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 50);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('scan_status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();

            // Reservation: bound to the exact idempotency attempt, not just the
            // idempotency row id (which survives a lease reclaim) — see
            // reservation_claim_token below.
            $table->foreignId('reserved_by_idempotency_key_id')->nullable()
                ->constrained('idempotency_keys')->nullOnDelete();
            $table->string('reservation_claim_token', 36)->nullable();
            $table->timestamp('reservation_expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'workflow_version_id']);
            $table->index('upload_session_token');
            $table->index('expires_at');
            $table->index(['reserved_by_idempotency_key_id', 'reservation_claim_token'], 'temp_uploads_reservation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_uploads');
    }
};

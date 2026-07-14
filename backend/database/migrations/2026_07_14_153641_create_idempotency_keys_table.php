<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('organization_id')->nullable()->constrained('organizations');
            $table->string('operation', 64);
            $table->string('request_fingerprint', 64);
            $table->enum('state', ['PROCESSING', 'COMPLETED'])->default('PROCESSING');
            $table->string('claim_token', 36);
            $table->timestamp('locked_until');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->foreignId('engine_request_id')->nullable()
                ->constrained('engine_requests')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'operation', 'key']);
            $table->index(['state', 'locked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};

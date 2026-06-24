<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions');
            $table->foreignId('current_stage_id')->constrained('workflow_stages');
            $table->string('reference')->unique();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('bank_id')->nullable()->constrained('banks');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants');
            $table->json('data')->nullable();
            $table->unsignedInteger('version')->default(1);

            // Hybrid projection columns (DI-2) — indexed for reports/filters, never scan JSON
            $table->decimal('amount', 18, 2)->nullable()->index();
            $table->string('currency', 10)->nullable()->index();
            $table->string('invoice_number', 100)->nullable()->index();

            $table->timestamps();

            $table->index('bank_id');
            $table->index('merchant_id');
            $table->index(['status', 'current_stage_id']);
            $table->index(['workflow_version_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_requests');
    }
};

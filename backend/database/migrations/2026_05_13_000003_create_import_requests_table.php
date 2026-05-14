<?php

use App\Enums\UserRole;
use App\Enums\RequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->string('supplier_name');
            $table->text('goods_description');
            $table->string('port_of_entry');
            $table->text('notes')->nullable();
            $table->string('status')->default(RequestStatus::DRAFT->value)->index();
            $table->string('current_owner_role')->default(UserRole::DATA_ENTRY->value)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('bank_approved_at')->nullable();
            $table->timestamp('support_approved_at')->nullable();
            $table->timestamp('swift_uploaded_at')->nullable();
            $table->timestamp('executive_decided_at')->nullable();
            $table->timestamp('customs_issued_at')->nullable();
            $table->unsignedInteger('revision_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('bank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_requests');
    }
};

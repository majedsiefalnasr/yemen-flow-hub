<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table) {
            // Actor tracking columns per docs/03-database-and-models.md
            $table->foreignId('last_updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('last_updated_by')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('submitted_by')->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->foreignId('resubmitted_by')->nullable()->after('rejected_by')->constrained('users')->nullOnDelete();
            $table->foreignId('support_reviewed_by')->nullable()->after('resubmitted_by')->constrained('users')->nullOnDelete();
            $table->foreignId('swift_uploaded_by')->nullable()->after('support_reviewed_by')->constrained('users')->nullOnDelete();
            $table->foreignId('voting_opened_by')->nullable()->after('swift_uploaded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('voting_opened_at')->nullable()->after('voting_opened_by');
            $table->foreignId('voting_closed_by')->nullable()->after('voting_opened_at')->constrained('users')->nullOnDelete();
            $table->timestamp('voting_closed_at')->nullable()->after('voting_closed_by');
            $table->string('voting_session_status')->nullable()->after('voting_closed_at');
            $table->timestamp('final_decision_at')->nullable()->after('voting_session_status');
            $table->foreignId('customs_declaration_id')->nullable()->after('final_decision_at')->constrained('customs_declarations')->nullOnDelete();

            // Required indexes (AC-4)
            $table->index('voting_session_status');
            $table->index('voting_opened_at');
            $table->index('voting_closed_at');
            $table->index('created_at');
            $table->index('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_updated_by');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('resubmitted_by');
            $table->dropConstrainedForeignId('support_reviewed_by');
            $table->dropConstrainedForeignId('swift_uploaded_by');
            $table->dropConstrainedForeignId('voting_opened_by');
            $table->dropConstrainedForeignId('voting_closed_by');
            $table->dropConstrainedForeignId('customs_declaration_id');
            $table->dropColumn([
                'voting_opened_at',
                'voting_closed_at',
                'voting_session_status',
                'final_decision_at',
            ]);
            $table->dropIndex(['voting_session_status']);
            $table->dropIndex(['voting_opened_at']);
            $table->dropIndex(['voting_closed_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['claimed_at']);
        });
    }
};

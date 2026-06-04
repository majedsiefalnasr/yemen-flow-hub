<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE import_requests MODIFY COLUMN status ENUM(
            'DRAFT','DRAFT_REJECTED_INTERNAL','SUBMITTED','BANK_REVIEW',
            'BANK_APPROVED','SUPPORT_REVIEW_PENDING','SUPPORT_REVIEW_IN_PROGRESS',
            'SUPPORT_APPROVED','SUPPORT_REJECTED','WAITING_FOR_SWIFT','SWIFT_UPLOADED',
            'WAITING_FOR_VOTING_OPEN','EXECUTIVE_VOTING_OPEN','EXECUTIVE_VOTING_CLOSED',
            'EXECUTIVE_APPROVED','EXECUTIVE_REJECTED','FX_CONFIRMATION_PENDING',
            'CUSTOMS_DECLARATION_ISSUED','COMPLETED',
            'BANK_RETURNED','SUPPORT_RETURNED','BANK_REJECTED'
        ) NOT NULL DEFAULT 'DRAFT'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE import_requests MODIFY COLUMN status ENUM(
            'DRAFT','DRAFT_REJECTED_INTERNAL','SUBMITTED','BANK_REVIEW',
            'BANK_APPROVED','SUPPORT_REVIEW_PENDING','SUPPORT_REVIEW_IN_PROGRESS',
            'SUPPORT_APPROVED','SUPPORT_REJECTED','WAITING_FOR_SWIFT','SWIFT_UPLOADED',
            'WAITING_FOR_VOTING_OPEN','EXECUTIVE_VOTING_OPEN','EXECUTIVE_VOTING_CLOSED',
            'EXECUTIVE_APPROVED','EXECUTIVE_REJECTED',
            'CUSTOMS_DECLARATION_ISSUED','COMPLETED',
            'BANK_RETURNED','SUPPORT_RETURNED','BANK_REJECTED'
        ) NOT NULL DEFAULT 'DRAFT'");
    }
};

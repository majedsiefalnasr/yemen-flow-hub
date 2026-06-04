<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 12.2 follow-up — code-review finding C6.
 *
 * Snapshot of the EXECUTIVE_MEMBER user IDs eligible to vote at the moment a
 * voting session opens (WAITING_FOR_VOTING_OPEN → EXECUTIVE_VOTING_OPEN).
 *
 * Why a per-session snapshot rather than the global active-member count:
 * the dashboard `votingQueueResource` previously read `User::where(role, ...)
 * ->where(is_active, true)->count()` and applied that single number to every
 * session. If a member was deactivated between casting their vote and the
 * dashboard render, `votes_cast` could exceed `total_voters` and the progress
 * bar would render above 100%. Snapshotting at session-open time eliminates
 * that race; deactivations after the session opens no longer affect the
 * displayed denominator.
 *
 * Backfill is intentionally NOT included here — sessions opened before this
 * migration ran simply lack the snapshot, and `votingQueueResource` falls back
 * to the legacy global count for those rows. New sessions get the snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table) {
            $table->json('eligible_voter_ids')->nullable()->after('voting_opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table) {
            $table->dropColumn('eligible_voter_ids');
        });
    }
};

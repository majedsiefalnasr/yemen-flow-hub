<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->whereIn('key', [
                'voting_session_timeout', 'support_committee_size', 'executive_committee_size',
                'minimum_quorum', 'review_timeout_hours', 'secret_voting', 'director_tiebreak',
                'notifications_phase_1_enabled', 'search_phase_1_enabled', 'customs_print_preview_enabled',
                'password_expiry_90_days', 'lockout_after_5_attempts', 'encrypt_uploads_aes256',
                'log_all_audit', 'allow_external_access',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Placebo rows intentionally not restored — they had no consumers.
    }
};

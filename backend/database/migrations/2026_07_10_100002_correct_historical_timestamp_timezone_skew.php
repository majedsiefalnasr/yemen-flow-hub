<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TZ-001: paired with the same-deploy config/database.php 'timezone' fix.
 *
 * Root cause: the mysql connection had no session timezone override, so PDO
 * defaulted to MySQL's SYSTEM variable (UTC on this host), while every write
 * path uses Carbon now() in config('app.timezone') (Asia/Aden, UTC+3).
 * Eloquent formats a Carbon instance to a naive wall-clock string before
 * binding it (no timezone info survives) -- MySQL's TIMESTAMP type then
 * stored that Aden-local string as if it were a UTC instant, so every
 * existing TIMESTAMP value's true stored UTC instant is
 * config('app.timezone')'s offset (3 hours for Asia/Aden) LATER than the
 * real-world moment it should represent.
 *
 * Verified this does NOT corrupt relative epoch math done entirely in SQL
 * under one session (e.g. SLA breach/nearing/ok classification, which only
 * ever compares column-derived epochs against UNIX_TIMESTAMP() under the
 * same connection) -- the skew cancels out there. The concrete, real
 * regression this migration guards against is display/export: once the
 * paired config fix makes the MySQL session timezone match
 * config('app.timezone'), TIMESTAMP-to-session-timezone conversion becomes
 * correct going forward, which means EXISTING rows would suddenly display
 * `offset` hours LATER than they always have (since the stored instant
 * itself is still off) unless corrected here in the same deploy.
 *
 * Correction: shift every affected TIMESTAMP column's stored instant back
 * by config('app.timezone')'s UTC offset (`- INTERVAL :offset SECOND`).
 * Verified this arithmetic is session-independent: manually confirmed on
 * the real dev DB that the same INTERVAL shift produces the same corrected
 * true-UTC value whether run under the Asia/Aden or the UTC session.
 *
 * Full column inventory (109 columns, 40 tables) captured via:
 *   SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
 *   WHERE TABLE_SCHEMA = <db> AND DATA_TYPE = 'timestamp'
 * -- every TIMESTAMP column in the schema; there are no DATETIME columns to
 * exclude (confirmed: zero rows for DATA_TYPE = 'datetime').
 *
 * Chunked by primary key range per table to bound lock duration on large
 * tables, same pattern as every other backfill migration in this
 * remediation pass.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const AFFECTED_COLUMNS = [
        'audit_log_archives' => ['archived_at', 'created_at'],
        'audit_logs' => ['created_at'],
        'banks' => ['created_at', 'updated_at'],
        'customs_declarations' => ['created_at', 'issued_at', 'signed_fx_doc_uploaded_at', 'updated_at'],
        'document_types' => ['created_at', 'updated_at'],
        'email_deliveries' => ['created_at', 'dispatched_at', 'failed_at', 'queued_at', 'sent_at', 'updated_at'],
        'engine_notifications' => ['created_at', 'updated_at'],
        'engine_request_documents' => ['created_at', 'deleted_at', 'updated_at'],
        'engine_requests' => ['claim_expires_at', 'claimed_at', 'created_at', 'stage_entered_at', 'updated_at'],
        'failed_jobs' => ['failed_at'],
        'field_definitions' => ['created_at', 'updated_at'],
        'field_groups' => ['created_at', 'updated_at'],
        'login_history' => ['created_at', 'logged_in_at', 'logged_out_at', 'updated_at'],
        'merchant_companies' => ['created_at', 'updated_at'],
        'merchant_owners' => ['created_at', 'updated_at'],
        'merchants' => ['created_at', 'deleted_at', 'updated_at'],
        'notification_recipients' => ['archived_at', 'created_at', 'read_at', 'updated_at'],
        'notification_template_versions' => ['created_at', 'updated_at'],
        'notification_templates' => ['created_at', 'updated_at'],
        'notifications' => ['created_at', 'read_at', 'updated_at'],
        'organizations' => ['created_at', 'updated_at'],
        'password_histories' => ['created_at'],
        'personal_access_tokens' => ['created_at', 'expires_at', 'last_used_at', 'updated_at'],
        'reference_tables' => ['created_at', 'updated_at'],
        'reference_values' => ['created_at', 'updated_at'],
        'report_exports' => ['created_at', 'updated_at'],
        'roles' => ['created_at', 'updated_at'],
        'scheduler_run_logs' => ['created_at', 'ran_at', 'updated_at'],
        'screen_permissions' => ['created_at', 'updated_at'],
        'screens' => ['created_at', 'updated_at'],
        'stage_field_rules' => ['created_at', 'updated_at'],
        'stage_permissions' => ['created_at', 'updated_at'],
        'system_settings' => ['created_at', 'updated_at'],
        'teams' => ['created_at', 'updated_at'],
        'trusted_devices' => ['created_at', 'expires_at', 'last_used_at', 'updated_at'],
        'user_roles' => ['created_at', 'updated_at'],
        'user_teams' => ['created_at', 'updated_at'],
        'users' => ['created_at', 'last_login_at', 'password_changed_at', 'temporary_password_set_at', 'updated_at'],
        'workflow_actions' => ['created_at', 'updated_at'],
        'workflow_definitions' => ['created_at', 'deleted_at', 'updated_at'],
        'workflow_history' => ['created_at'],
        'workflow_history_archives' => ['archived_at', 'created_at'],
        'workflow_stages' => ['created_at', 'updated_at'],
        'workflow_transitions' => ['created_at', 'updated_at'],
        'workflow_versions' => ['created_at', 'published_at', 'updated_at'],
    ];

    private const CHUNK_SIZE = 5000;

    public function up(): void
    {
        $this->shift(direction: -1);
    }

    public function down(): void
    {
        $this->shift(direction: 1);
    }

    private function shift(int $direction): void
    {
        $offsetSeconds = Carbon::now(config('app.timezone'))->getOffset();
        if ($offsetSeconds === 0) {
            // app.timezone is UTC (or otherwise zero-offset) -- nothing to correct.
            return;
        }

        $signedOffset = $direction * $offsetSeconds;

        foreach (self::AFFECTED_COLUMNS as $table => $columns) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $primaryKey = $this->primaryKeyColumn($table);
            $lastId = 0;

            while (true) {
                $ids = DB::table($table)
                    ->where($primaryKey, '>', $lastId)
                    ->orderBy($primaryKey)
                    ->limit(self::CHUNK_SIZE)
                    ->pluck($primaryKey);

                if ($ids->isEmpty()) {
                    break;
                }

                $updates = [];
                foreach ($columns as $column) {
                    $updates[$column] = DB::raw("CASE WHEN `{$column}` IS NOT NULL THEN `{$column}` + INTERVAL ({$signedOffset}) SECOND ELSE NULL END");
                }

                DB::table($table)
                    ->whereIn($primaryKey, $ids)
                    ->update($updates);

                $lastId = $ids->last();
            }
        }
    }

    private function primaryKeyColumn(string $table): string
    {
        // Every table in AFFECTED_COLUMNS uses a plain auto-increment `id`
        // primary key (confirmed via schema inspection) -- no composite or
        // non-standard primary keys among them.
        return 'id';
    }
};

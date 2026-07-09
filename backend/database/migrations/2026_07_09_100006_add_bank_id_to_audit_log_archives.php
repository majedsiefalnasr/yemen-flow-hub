<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCH-006: audit_log_archives predates SEC-002's audit_logs.bank_id column,
 * so AuditLogArchiveService::archiveBatch() has been silently dropping
 * bank_id the moment a row archives — an archived row becomes unscopable
 * (invisible to the bank admin it belongs to). Adds the column and backfills
 * existing archived rows using the same subject-resolution rule as
 * AuditService::resolveBankId() / the audit_logs backfill (SEC-002): User /
 * Merchant / EngineRequest subjects use their own bank_id; Bank subjects use
 * their own id; anything else (no bank concept) stays null, never guessed
 * from the acting user.
 *
 * Chunked by audit_log_archives.id range so backfill does not lock the whole
 * table on a large dataset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log_archives', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('subject_id');
            $table->index(['bank_id', 'archived_at'], 'ala_bank_archived');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('audit_log_archives', function (Blueprint $table) {
            $table->dropIndex('ala_bank_archived');
            $table->dropColumn('bank_id');
        });
    }

    private function backfill(): void
    {
        $chunk = 5000;
        $lastId = 0;

        // subject_type => bank-resolving table
        $directBankColumn = [
            'App\\Models\\User' => 'users',
            'App\\Models\\Merchant' => 'merchants',
            'App\\Models\\EngineRequest' => 'engine_requests',
        ];
        $selfIsBank = 'App\\Models\\Bank';

        while (true) {
            $rows = DB::table('audit_log_archives')
                ->select('id', 'subject_type', 'subject_id')
                ->where('id', '>', $lastId)
                ->whereNotNull('subject_type')
                ->whereNotNull('subject_id')
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $bankId = null;

                if ($row->subject_type === $selfIsBank) {
                    $bankId = (int) $row->subject_id;
                } elseif (isset($directBankColumn[$row->subject_type])) {
                    $bankId = DB::table($directBankColumn[$row->subject_type])->where('id', $row->subject_id)->value('bank_id');
                }

                if ($bankId !== null) {
                    DB::table('audit_log_archives')->where('id', $row->id)->update(['bank_id' => $bankId]);
                }
            }

            $lastId = $rows->last()->id;
        }
    }
};

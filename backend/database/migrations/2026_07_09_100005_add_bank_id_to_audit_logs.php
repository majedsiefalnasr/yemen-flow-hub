<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-002: add bank_id to audit_logs so bank-scoped reads are possible.
 * AuditLogController::show() previously denied ALL non-systemWide users
 * outright because there was no column to scope against.
 *
 * Backfill derives bank_id from each row's subject: User.bank_id,
 * Merchant.bank_id, EngineRequest.bank_id directly; Bank subjects use their
 * own id. Rows whose subject has no bank concept (Organization, Role,
 * settings, null subject) stay null — never guessed from the acting user,
 * since a CBY staff actor is not bank-scoped and guessing would misattribute
 * the row to the wrong bank.
 *
 * Chunked by audit_logs.id range so backfill does not lock the whole table
 * on a large dataset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable()->after('subject_id');
            $table->index(['bank_id', 'created_at'], 'al_bank_created');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('al_bank_created');
            $table->dropColumn('bank_id');
        });
    }

    private function backfill(): void
    {
        $chunk = 5000;
        $lastId = 0;

        // subject_type => [table, bank-resolving column/expression]
        $directBankColumn = [
            'App\\Models\\User' => 'bank_id',
            'App\\Models\\Merchant' => 'bank_id',
            'App\\Models\\EngineRequest' => 'bank_id',
        ];
        $selfIsBank = 'App\\Models\\Bank';

        while (true) {
            $rows = DB::table('audit_logs')
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
                    $table = match ($row->subject_type) {
                        'App\\Models\\User' => 'users',
                        'App\\Models\\Merchant' => 'merchants',
                        'App\\Models\\EngineRequest' => 'engine_requests',
                    };
                    $bankId = DB::table($table)->where('id', $row->subject_id)->value('bank_id');
                }

                if ($bankId !== null) {
                    DB::table('audit_logs')->where('id', $row->id)->update(['bank_id' => $bankId]);
                }
            }

            $lastId = $rows->last()->id;
        }
    }
};

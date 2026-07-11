<?php

namespace App\Services\Workflow;

use App\Exceptions\EngineException;
use Illuminate\Support\Facades\DB;

/**
 * API-003b: allocates the next engine-request reference by atomically
 * incrementing a single per-year row in `engine_request_reference_sequences`.
 *
 * This replaces the old MAX(CAST(suffix AS UNSIGNED))+1 derivation, which had
 * every concurrent creator race the same value and collide on the unique
 * `reference` index — under true parallelism MySQL aborts the losing INSERT
 * with a 1213 deadlock. A single-row atomic increment serializes allocation on
 * one hot row (no index-gap race), so the deadlock class cannot occur.
 *
 * Allocation is committed on its own, BEFORE the caller's create transaction,
 * so a create rollback (or a deadlock-retry of that transaction) leaves an
 * unused number — a harmless gap. References must be unique and monotonic, not
 * gapless, so skipped numbers are acceptable and expected.
 */
class EngineRequestReferenceAllocator
{
    /**
     * Reserve and return the next reference string, e.g. "ENG-2026-000123".
     */
    public function allocate(): string
    {
        $year = (string) now()->year;
        $sequence = $this->nextSequence($year);

        return sprintf('ENG-%s-%06d', $year, $sequence);
    }

    /**
     * Atomically bump and return the per-year counter. Both statements are a
     * single round-trip upsert — no read-then-write window, no row lock held
     * across PHP, no gap-lock on the reference index.
     */
    private function nextSequence(string $year): int
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => $this->nextSequenceMysql($year),
            'sqlite' => $this->nextSequenceSqlite($year),
            default => throw new EngineException(
                "Reference allocation is not supported on the [{$driver}] driver.",
                'REFERENCE_ALLOCATION_FAILED',
                500,
            ),
        };
    }

    /**
     * MySQL: INSERT ... ON DUPLICATE KEY UPDATE with LAST_INSERT_ID(expr) so the
     * post-increment value is returned by LAST_INSERT_ID() on the same
     * connection, with no separate SELECT. A fresh year inserts last_value = 1;
     * an existing year advances it by one. Single-row, deadlock-free.
     */
    private function nextSequenceMysql(string $year): int
    {
        // `year` is a MySQL reserved word — backtick-quote every identifier.
        DB::statement(
            'INSERT INTO `engine_request_reference_sequences` (`year`, `last_value`, `created_at`, `updated_at`) '
            .'VALUES (?, 1, NOW(), NOW()) '
            .'ON DUPLICATE KEY UPDATE `last_value` = LAST_INSERT_ID(`last_value` + 1), `updated_at` = NOW()',
            [$year],
        );

        return (int) DB::selectOne('SELECT LAST_INSERT_ID() AS value')->value;
    }

    /**
     * SQLite (test suite): INSERT ... ON CONFLICT DO UPDATE ... RETURNING is
     * atomic and returns the post-increment value directly (SQLite 3.35+ for
     * RETURNING, 3.24+ for upsert). LAST_INSERT_ID has no SQLite analogue, so
     * RETURNING is the portable equivalent.
     */
    private function nextSequenceSqlite(string $year): int
    {
        $now = now()->toDateTimeString();

        // `year` is quoted for parity with the MySQL path (SQLite accepts
        // backtick identifiers). RETURNING is SQLite's atomic analogue of
        // MySQL's LAST_INSERT_ID(expr).
        $row = DB::selectOne(
            'INSERT INTO `engine_request_reference_sequences` (`year`, `last_value`, `created_at`, `updated_at`) '
            .'VALUES (?, 1, ?, ?) '
            .'ON CONFLICT(`year`) DO UPDATE SET `last_value` = `last_value` + 1, `updated_at` = excluded.`updated_at` '
            .'RETURNING `last_value`',
            [$year, $now, $now],
        );

        return (int) $row->last_value;
    }
}

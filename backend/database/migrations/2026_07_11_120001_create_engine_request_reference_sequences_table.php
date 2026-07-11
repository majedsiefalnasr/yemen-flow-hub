<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API-003b: a per-year sequence table for engine-request references.
 *
 * The prior allocator derived the next number from MAX(CAST(suffix AS UNSIGNED))+1
 * on engine_requests, so every concurrent creator raced the same value and the
 * losers collided on the unique `reference` index — under true parallelism MySQL
 * aborts the losing INSERT with a 1213 deadlock, which the deadlock-retry (API-003)
 * absorbs only up to a point (6+ parallel creators on the same index gap exhaust
 * the retry budget). This table replaces that with an atomic single-row
 * increment (INSERT ... ON DUPLICATE KEY UPDATE / ON CONFLICT), which serializes
 * allocation on one hot row instead of racing the whole index — removing the
 * deadlock class entirely. Mirrors the shape of the dropped legacy
 * import_request_reference_sequences table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_request_reference_sequences', function (Blueprint $table) {
            $table->string('year', 4)->primary();
            $table->unsignedInteger('last_value');
            $table->timestamps();
        });

        // Backfill the current year so the first allocation continues past any
        // existing ENG-{year}-* references rather than colliding with them.
        // Numeric-cast MAX (not a lexicographic string MAX) so a 7-digit suffix
        // is ordered correctly above a 6-digit one — the exact bug API-003 fixed
        // in the old derivation, preserved here for the one-time seed.
        $year = (string) now()->year;
        $prefix = "ENG-{$year}-";
        $maxSuffix = DB::table('engine_requests')
            ->where('reference', 'like', $prefix.'%')
            ->max(DB::raw('CAST(SUBSTRING(reference, '.(strlen($prefix) + 1).') AS UNSIGNED)'));

        DB::table('engine_request_reference_sequences')->insert([
            'year' => $year,
            'last_value' => (int) ($maxSuffix ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_request_reference_sequences');
    }
};

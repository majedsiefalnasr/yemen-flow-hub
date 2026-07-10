<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * TZ-001 verification tool. Proves whether MySQL TIMESTAMP columns store the
 * true UTC instant a Carbon now() call represents, or whether they silently
 * store naive config('app.timezone') wall-clock under a mismatched MySQL
 * session timezone (the bug: every historical timestamp ends up
 * config('app.timezone')'s UTC-offset-worth of hours ahead of the real
 * moment it should represent, since MySQL's session time_zone defaults to
 * SYSTEM and this connection previously had no 'timezone' override).
 *
 * Invisible to the normal PHPUnit suite: phpunit.xml forces sqlite, which
 * has no session-timezone concept at all, so this can only be verified
 * against a real MySQL connection.
 *
 * Usage: php artisan tz:verify
 */
class TzVerifyCommand extends Command
{
    protected $signature = 'tz:verify';

    protected $description = 'TZ-001: verify MySQL TIMESTAMP columns store the true UTC instant, not app-timezone-skewed wall-clock';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('This command must run against the mysql connection -- the bug being verified is MySQL session-timezone-specific and does not exist on sqlite.');

            return self::FAILURE;
        }

        $appTimezone = config('app.timezone');
        $sessionTimezone = DB::selectOne('SELECT @@session.time_zone as tz')->tz;

        $this->info("config('app.timezone'): {$appTimezone}");
        $this->info("MySQL session time_zone: {$sessionTimezone}");
        $this->newLine();

        $user = User::query()->first();
        if ($user === null) {
            $this->error('No users found to run the round-trip check against.');

            return self::FAILURE;
        }

        $originalValue = $user->last_login_at;

        $before = now();
        $user->last_login_at = $before;
        $user->save();

        $rawStored = DB::table('users')->where('id', $user->id)->value('last_login_at');
        $expectedTrueUtcEpoch = $before->clone()->utc()->getTimestamp();

        // The bug does not show up in a same-session round trip (write and
        // read both misinterpret the same wall-clock string the same way,
        // so it cancels out) -- it shows up when raw SQL treats the stored
        // string as a timezone-independent instant, which is exactly what
        // every EngineRequest::epochSql()/UNIX_TIMESTAMP(column) call does
        // throughout the SLA deadline/breach logic. UNIX_TIMESTAMP() with no
        // argument is always the true current UTC epoch, independent of
        // session timezone -- compare the stored column's SQL-computed
        // epoch against that ground truth.
        $sqlComputedEpoch = (int) DB::table('users')
            ->where('id', $user->id)
            ->value(DB::raw('UNIX_TIMESTAMP(last_login_at) as epoch'));

        // Restore original state.
        DB::table('users')->where('id', $user->id)->update(['last_login_at' => $originalValue]);

        $driftSeconds = $sqlComputedEpoch - $expectedTrueUtcEpoch;

        $this->line("Wrote now(): {$before->toDateTimeString()} ({$appTimezone} local)");
        $this->line("True UTC epoch (from PHP/Carbon): {$expectedTrueUtcEpoch}");
        $this->line("Raw value MySQL stored: {$rawStored}");
        $this->line("UNIX_TIMESTAMP(stored column) as computed by raw SQL: {$sqlComputedEpoch}");
        $this->line('Drift: '.$driftSeconds.'s ('.round($driftSeconds / 3600, 2).'h)');
        $this->newLine();

        if ($driftSeconds === 0) {
            $this->info('PASS: UNIX_TIMESTAMP(column) agrees with the true UTC instant. No TZ-001 drift.');

            return self::SUCCESS;
        }

        $this->error("FAIL: UNIX_TIMESTAMP(column) is off by {$driftSeconds}s from the true UTC instant -- TZ-001 is present. Any raw SQL comparing this column's epoch against UNIX_TIMESTAMP() (no-arg) or another epoch source will be wrong by this amount.");

        return self::FAILURE;
    }
}

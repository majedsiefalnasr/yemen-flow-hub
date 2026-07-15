<?php

namespace Tests\Integration\Workflow;

use App\Models\IdempotencyKey;
use App\Models\TemporaryUpload;
use App\Services\Workflow\IdempotencyCoordinator;
use App\Services\Workflow\TemporaryUploadReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/**
 * tests/Unit/Services/Workflow/LeaseRenewalTest.php (SQLite-backed) cannot
 * actually prove the false-negative it documents: SQLite's UPDATE always
 * reports rows matched, never rows changed, so a same-value write there
 * still returns an affected count of 1 and every test there passes even
 * against the unfixed implementation (independently confirmed: SQLite
 * rowCount() === 1 for a same-value UPDATE). MySQL's PDO driver reports
 * rows *changed* by default (no PDO::MYSQL_ATTR_FOUND_ROWS override in
 * config/database.php), so a same-value UPDATE legitimately returns 0 —
 * this is the actual mechanism behind the SUBMISSION_LEASE_LOST false
 * negative, and only a real MySQL connection can exercise it.
 *
 * ISOLATION — read before touching this file:
 *
 * - This suite never runs as part of a bare `php artisan test` / `phpunit`
 *   invocation: it lives under tests/Integration, which phpunit.xml (the
 *   default config both artisan test and composer's "test" script use) does
 *   not declare as a testsuite at all — PHPUnit only discovers tests inside
 *   testsuites its active config actually lists. Isolation therefore comes
 *   from a dedicated phpunit.integration.xml, not from a group exclusion on
 *   the default config (an --exclude-group flag on phpunit.xml would still
 *   require phpunit.xml to list this directory in the first place). Run it
 *   deliberately:
 *
 *       composer test:mysql-integration
 *
 *   which resolves to:
 *
 *       vendor/bin/phpunit -c phpunit.integration.xml
 *
 *   The #[Group('mysql-integration')] attribute below is a secondary,
 *   independent filter — useful if this directory ever gains a test that
 *   should run under the default config — not what provides isolation here.
 *
 * - Requires five LEASE_MYSQL_TEST_* environment variables (host, port,
 *   database, username, password) to be set explicitly — see
 *   requiredEnv() below. There is no fallback to the application's own
 *   DB_HOST/DB_USERNAME/DB_PASSWORD: this suite must never be able to
 *   accidentally point at the real app connection just because that
 *   happens to be configured. Because the group is opt-in, a missing
 *   variable or an unreachable server FAILS the test (via
 *   RuntimeException in setUp(), never markTestSkipped()) — once you've
 *   asked for this gate, an environment problem is a real failure, not a
 *   quiet no-op.
 *
 * - assertSafeDatabaseName() refuses to run against a database whose name
 *   doesn't contain "test" (case-insensitive) or that matches one of this
 *   project's real application database names (cby_imports, yfh_audit) —
 *   even if those names happen to appear in the environment by mistake.
 *
 * - No shared table is ever touched. Every table this suite creates is
 *   named via a random per-process prefix (see uniquePrefix(), regenerated
 *   in setUp() for every single test method) applied through the
 *   connection's own `prefix` config — Laravel's query grammar applies
 *   this transparently, so IdempotencyKey::query()/TemporaryUpload::
 *   query() (unmodified, exactly as the real services under test call
 *   them) resolve to e.g. `lrg_f3a1_idempotency_keys` on the wire, never
 *   the literal `idempotency_keys` / `temporary_uploads` names — even
 *   under concurrent runs against the same database. tearDown() drops
 *   only those two prefixed tables it created, in a finally block, so a
 *   failed assertion or a failure partway through setUp() still cleans up
 *   and still restores Carbon::setTestNow() and the default connection.
 */
#[Group('mysql-integration')]
class LeaseRenewalMysqlRegressionTest extends TestCase
{
    private const CONNECTION = 'mysql_lease_regression_gate';

    private const REQUIRED_ENV_KEYS = [
        'LEASE_MYSQL_TEST_HOST',
        'LEASE_MYSQL_TEST_PORT',
        'LEASE_MYSQL_TEST_DATABASE',
        'LEASE_MYSQL_TEST_USERNAME',
        'LEASE_MYSQL_TEST_PASSWORD',
    ];

    /** Real application database names this suite must never be pointed at, even by accident. */
    private const FORBIDDEN_DATABASE_NAMES = ['cby_imports', 'yfh_audit', 'mysql', 'information_schema', 'performance_schema', 'sys'];

    private string $originalDefaultConnection;

    private string $tablePrefix;

    private bool $schemaCreated = false;

    /**
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    private function requiredEnv(): array
    {
        $missing = array_values(array_filter(
            self::REQUIRED_ENV_KEYS,
            fn (string $key): bool => env($key) === null || env($key) === '',
        ));

        if ($missing !== []) {
            throw new RuntimeException(
                'mysql-integration gate requires all of '.implode(', ', self::REQUIRED_ENV_KEYS).
                ' to be set explicitly. Missing: '.implode(', ', $missing).
                '. This group was explicitly requested (composer test:mysql-integration) — '.
                'an incomplete environment is a real failure here, not something to skip.',
            );
        }

        return [
            'host' => (string) env('LEASE_MYSQL_TEST_HOST'),
            'port' => (string) env('LEASE_MYSQL_TEST_PORT'),
            'database' => (string) env('LEASE_MYSQL_TEST_DATABASE'),
            'username' => (string) env('LEASE_MYSQL_TEST_USERNAME'),
            'password' => (string) env('LEASE_MYSQL_TEST_PASSWORD'),
        ];
    }

    private function assertSafeDatabaseName(string $database): void
    {
        $lower = strtolower($database);

        foreach (self::FORBIDDEN_DATABASE_NAMES as $forbidden) {
            if ($lower === strtolower($forbidden)) {
                throw new RuntimeException(
                    "LEASE_MYSQL_TEST_DATABASE ('{$database}') names a real application or system ".
                    'database. Refusing to run — point this at a dedicated, disposable test database.',
                );
            }
        }

        if (! str_contains($lower, 'test')) {
            throw new RuntimeException(
                "LEASE_MYSQL_TEST_DATABASE ('{$database}') does not contain 'test'. Refusing to run — ".
                'name the target database so its purpose is unambiguous, e.g. cby_lease_regression_test.',
            );
        }
    }

    private function uniquePrefix(): string
    {
        // Per-process AND per-test-method: two parallel test runners (or two
        // methods in the same run, since setUp() regenerates this) can never
        // collide on table names even inside the same shared test database.
        return 'lrg_'.getmypid().'_'.bin2hex(random_bytes(4)).'_';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $env = $this->requiredEnv();
        $this->assertSafeDatabaseName($env['database']);

        try {
            // dbname in the DSN itself (not a bare host:port connection
            // followed by a same-value SELECT, which would prove nothing
            // beyond server reachability): PDO's own connect step fails
            // immediately if the named database doesn't exist or these
            // credentials can't reach it, which is exactly what needs
            // confirming before this suite tries to create tables inside it.
            $pdo = new PDO(
                "mysql:host={$env['host']};port={$env['port']};dbname={$env['database']}",
                $env['username'],
                $env['password'],
                [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $pdo = null;
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'mysql-integration gate could not connect to the configured MySQL server ('.
                "{$env['host']}:{$env['port']}): {$exception->getMessage()}. This group was explicitly ".
                'requested — an unreachable server is a real failure here, not something to skip.',
                previous: $exception,
            );
        }

        $this->tablePrefix = $this->uniquePrefix();

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'mysql',
            'host' => $env['host'],
            'port' => $env['port'],
            'database' => $env['database'],
            'username' => $env['username'],
            'password' => $env['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => $this->tablePrefix,
            'strict' => true,
            // Deliberately no PDO::MYSQL_ATTR_FOUND_ROWS override — this is
            // exactly the production config/database.php mysql connection's
            // behavior, and the bug only reproduces without it.
        ]]);

        $this->originalDefaultConnection = config('database.default');
        config(['database.default' => self::CONNECTION]);
        DB::purge(self::CONNECTION);

        try {
            $this->createSchema();
            $this->schemaCreated = true;
        } catch (\Throwable $exception) {
            // createSchema() partially failed (e.g. first table created,
            // second failed) — clean up whatever did get created before
            // rethrowing, since tearDown() will still run for this test but
            // schemaCreated only flips true after a full success.
            $this->dropSchemaIfPresent();
            $this->restoreConnection();
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            Carbon::setTestNow();
        } finally {
            try {
                if ($this->schemaCreated) {
                    $this->dropSchemaIfPresent();
                }
            } finally {
                $this->restoreConnection();
            }
        }

        parent::tearDown();
    }

    private function restoreConnection(): void
    {
        if (isset($this->originalDefaultConnection)) {
            config(['database.default' => $this->originalDefaultConnection]);
        }
        config(['database.connections.'.self::CONNECTION => null]);
        DB::purge(self::CONNECTION);
    }

    private function dropSchemaIfPresent(): void
    {
        // Table names are resolved through the connection's own prefix
        // config, so these calls only ever reach the uniquely prefixed
        // tables this exact test method created — never the literal
        // idempotency_keys / temporary_uploads names, shared or otherwise.
        Schema::connection(self::CONNECTION)->dropIfExists('temporary_uploads');
        Schema::connection(self::CONNECTION)->dropIfExists('idempotency_keys');
    }

    private function createSchema(): void
    {
        Schema::connection(self::CONNECTION)->create('idempotency_keys', function ($table) {
            $table->id();
            $table->string('key', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('operation', 64)->nullable();
            $table->string('request_fingerprint', 64)->nullable();
            $table->string('state', 16)->default('PROCESSING');
            $table->string('claim_token', 36);
            $table->timestamp('locked_until');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedBigInteger('engine_request_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection(self::CONNECTION)->create('temporary_uploads', function ($table) {
            $table->id();
            $table->string('token', 64)->nullable();
            $table->string('upload_session_token', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('workflow_version_id')->nullable();
            $table->unsignedBigInteger('field_id')->nullable();
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('scan_status', 16)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedBigInteger('reserved_by_idempotency_key_id')->nullable();
            $table->string('reservation_claim_token', 36)->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function makeIdempotencyKey(string $claimToken, Carbon $lockedUntil): IdempotencyKey
    {
        return IdempotencyKey::query()->create([
            'key' => (string) Str::uuid(),
            'user_id' => 1,
            'organization_id' => 1,
            'operation' => IdempotencyCoordinator::OPERATION_ENGINE_REQUEST_CREATE,
            'request_fingerprint' => 'fp-'.Str::random(8),
            'state' => 'PROCESSING',
            'claim_token' => $claimToken,
            'locked_until' => $lockedUntil,
        ]);
    }

    private function makeTemporaryUpload(int $keyId, string $claimToken, Carbon $reservationExpiresAt): TemporaryUpload
    {
        return TemporaryUpload::query()->create([
            'token' => Str::random(40),
            'upload_session_token' => Str::random(40),
            'user_id' => 1,
            'organization_id' => 1,
            'workflow_version_id' => 1,
            'original_name' => 'file.pdf',
            'path' => 'private-tmp/file.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'checksum' => hash('sha256', 'x'),
            'scan_status' => 'clean',
            'expires_at' => now()->addHour(),
            'reserved_by_idempotency_key_id' => $keyId,
            'reservation_claim_token' => $claimToken,
            'reservation_expires_at' => $reservationExpiresAt,
        ]);
    }

    /**
     * Proves the mechanism directly, independent of the app's service layer:
     * a same-value UPDATE against real MySQL reports 0 affected rows even
     * though the WHERE predicate matched, while a different-value UPDATE
     * reports 1. This is the empirical basis for why renewLease()/renew()
     * needed a fallback at all.
     */
    public function test_mysql_update_reports_zero_affected_rows_for_a_same_value_write(): void
    {
        $key = $this->makeIdempotencyKey((string) Str::uuid(), now()->addSeconds(120));

        $sameValueAffected = IdempotencyKey::query()
            ->whereKey($key->id)
            ->update(['locked_until' => $key->locked_until]);

        $differentValueAffected = IdempotencyKey::query()
            ->whereKey($key->id)
            ->update(['locked_until' => $key->locked_until->copy()->addSecond()]);

        $this->assertSame(0, $sameValueAffected, 'MySQL must report 0 affected rows for a same-value UPDATE.');
        $this->assertSame(1, $differentValueAffected, 'MySQL must report 1 affected row once the value actually changes.');
    }

    /**
     * The actual regression gate: calls the real, unmodified service methods
     * against MySQL with time frozen inside one second. On the parent
     * (pre-fix) implementation this fails with false — 0 affected rows was
     * read as "lease lost." On the corrected implementation it passes.
     */
    public function test_idempotency_renewal_immediately_after_claim_in_the_same_second_succeeds_on_real_mysql(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $claimToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($claimToken, now()->addSeconds(120));

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $coordinator = app(IdempotencyCoordinator::class);
        $result = $coordinator->renewLease($key->id, $claimToken, 120);

        $this->assertTrue(
            $result,
            'renewLease() must return true against real MySQL for a same-second renewal that matched the row.',
        );
    }

    public function test_reservation_renewal_immediately_after_reserve_in_the_same_second_succeeds_on_real_mysql(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $claimToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($claimToken, now()->addSeconds(120));
        $upload = $this->makeTemporaryUpload($key->id, $claimToken, now()->addSeconds(120));

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $reservations = app(TemporaryUploadReservationService::class);
        $result = $reservations->renew([$upload->id], $key->id, $claimToken, 120);

        $this->assertTrue(
            $result,
            'renew() must return true against real MySQL for a same-second renewal that matched the row.',
        );
    }

    public function test_idempotency_renewal_with_the_wrong_claim_token_still_returns_false_on_real_mysql(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $realToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($realToken, now()->addSeconds(120));

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $coordinator = app(IdempotencyCoordinator::class);
        $result = $coordinator->renewLease($key->id, (string) Str::uuid(), 120);

        $this->assertFalse($result, 'A renewal under the wrong claim_token must still fail on real MySQL.');
    }

    public function test_idempotency_renewal_after_the_lease_was_actually_reclaimed_still_returns_false_on_real_mysql(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $originalToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($originalToken, now()->subSeconds(1));

        $key->update(['claim_token' => (string) Str::uuid(), 'locked_until' => now()->addSeconds(120)]);

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $coordinator = app(IdempotencyCoordinator::class);
        $result = $coordinator->renewLease($key->id, $originalToken, 120);

        $this->assertFalse(
            $result,
            'A superseded attempt must still fail on real MySQL once the lease has genuinely been reclaimed.',
        );
    }
}

<?php

namespace Tests\Unit\Services\Workflow;

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
use Tests\TestCase;

/**
 * LeaseRenewalTest.php (the SQLite-backed suite) cannot actually prove the
 * false-negative it documents: SQLite's UPDATE always reports rows matched,
 * never rows changed, so a same-value write there still returns an affected
 * count of 1 and every test there passes even against the unfixed
 * implementation (independently confirmed: SQLite rowCount() === 1 for a
 * same-value UPDATE). MySQL's PDO driver reports rows *changed* by default
 * (no PDO::MYSQL_ATTR_FOUND_ROWS override in config/database.php), so a
 * same-value UPDATE legitimately returns 0 — this is the actual mechanism
 * behind the SUBMISSION_LEASE_LOST false negative, and only a real MySQL
 * connection can exercise it.
 *
 * This suite talks to a disposable database inside the project's existing
 * MySQL 8.4 dev container (see docker-compose / yfh-mysql), never the real
 * application database (cby_imports). It reuses yfh_migrate_check — a
 * database the app's own DB_USERNAME already has full grants on for
 * exactly this kind of throwaway verification (the app user has no
 * CREATE DATABASE privilege on arbitrary names, only on cby_imports,
 * yfh_audit, and yfh_migrate_check specifically). It creates two minimal,
 * FK-free tables that carry only the columns renewLease()/renew() touch,
 * points IdempotencyKey/TemporaryUpload's default connection at them for
 * the duration of each test, and drops those tables again in tearDown()
 * (never the database itself, which other tooling may also share). If
 * MySQL isn't reachable (CI/sandboxes without Docker), every test here is
 * marked skipped rather than failed.
 */
class LeaseRenewalMysqlRegressionTest extends TestCase
{
    private const CONNECTION = 'mysql_lease_regression_gate';

    private const DATABASE = 'yfh_migrate_check';

    private static bool $mysqlAvailable = false;

    private static ?string $skipReason = null;

    private string $originalDefaultConnection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $host = env('DB_HOST', '127.0.0.1');
        $port = (int) env('DB_PORT', 3306);
        $username = env('DB_USERNAME', 'cby');
        $password = env('DB_PASSWORD', 'cby_password');

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port}",
                $username,
                $password,
                [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            // IF NOT EXISTS, not DROP-then-CREATE: this database is shared
            // with other throwaway verification, so this suite only ever
            // owns and cleans up its own two tables (see tearDown()), never
            // the database itself.
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.self::DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            self::$mysqlAvailable = true;
        } catch (PDOException $exception) {
            self::$mysqlAvailable = false;
            self::$skipReason = 'MySQL not reachable for the lease-renewal regression gate: '.$exception->getMessage();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$mysqlAvailable) {
            $this->markTestSkipped(self::$skipReason ?? 'MySQL not available.');
        }

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => self::DATABASE,
            'username' => env('DB_USERNAME', 'cby'),
            'password' => env('DB_PASSWORD', 'cby_password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            // Deliberately no PDO::MYSQL_ATTR_FOUND_ROWS override — this is
            // exactly the production config/database.php mysql connection's
            // behavior, and the bug only reproduces without it.
        ]]);

        $this->originalDefaultConnection = config('database.default');
        config(['database.default' => self::CONNECTION]);
        DB::purge(self::CONNECTION);

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (self::$mysqlAvailable) {
            Schema::connection(self::CONNECTION)->dropIfExists('temporary_uploads');
            Schema::connection(self::CONNECTION)->dropIfExists('idempotency_keys');
            config(['database.default' => $this->originalDefaultConnection]);
            DB::purge(self::CONNECTION);
        }

        parent::tearDown();
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

        Carbon::setTestNow();
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

        Carbon::setTestNow();
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

        Carbon::setTestNow();
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

        Carbon::setTestNow();
    }
}

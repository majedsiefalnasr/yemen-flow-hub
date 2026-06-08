<?php

namespace Tests\Feature\Financing;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\FinancingLimitExceededException;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\FinancingLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FinancingLedgerTest extends TestCase
{
    use RefreshDatabase;

    private FinancingLedgerService $service;

    private Bank $bank1;

    private Bank $bank2;

    private User $creator1;

    private User $creator2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FinancingLedgerService::class);
        $this->bank1 = Bank::query()->create(['name' => 'Bank One', 'code' => 'B1', 'is_active' => true]);
        $this->bank2 = Bank::query()->create(['name' => 'Bank Two', 'code' => 'B2', 'is_active' => true]);
        $this->creator1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank1);
        $this->creator2 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank2);
    }

    public function test_composite_index_exists_on_import_requests(): void
    {
        $this->assertTrue($this->compositeIndexExists());
    }

    public function test_not_eligible_set_contains_exactly_terminal_non_approved_cases(): void
    {
        $expected = [
            RequestStatus::BANK_REJECTED,
            RequestStatus::SUPPORT_REJECTED,
            RequestStatus::EXECUTIVE_REJECTED,
            RequestStatus::DRAFT_REJECTED_INTERNAL,
        ];

        $this->assertSame($expected, FinancingLedgerService::NOT_ELIGIBLE_STATUSES);

        foreach (RequestStatus::cases() as $status) {
            $shouldExclude = in_array($status, $expected, true);
            $isExcluded = in_array($status->value, FinancingLedgerService::notEligibleStatusValues(), true);

            $this->assertSame(
                $shouldExclude,
                $isExcluded,
                sprintf('Unexpected not_eligible_set membership for %s', $status->value)
            );
        }
    }

    public function test_used_percent_sums_across_banks_and_excludes_not_eligible_statuses(): void
    {
        $taxNumber = 'TAX-1001';
        $invoiceNumber = 'INV-1001';

        $this->makeFinancingRequest($this->bank1, $this->creator1, $taxNumber, $invoiceNumber, 40.00, RequestStatus::SUBMITTED);
        $this->makeFinancingRequest($this->bank2, $this->creator2, $taxNumber, $invoiceNumber, 25.00, RequestStatus::BANK_REVIEW);
        $this->makeFinancingRequest($this->bank1, $this->creator1, $taxNumber, $invoiceNumber, 50.00, RequestStatus::BANK_REJECTED);
        $this->makeFinancingRequest($this->bank2, $this->creator2, $taxNumber, $invoiceNumber, 30.00, RequestStatus::EXECUTIVE_APPROVED);

        $this->assertSame(95.0, $this->service->usedPercent($taxNumber, $invoiceNumber));
        $this->assertSame(5.0, $this->service->remainingPercent($taxNumber, $invoiceNumber));
        $this->assertTrue($this->service->wouldExceed($taxNumber, $invoiceNumber, 6.00));
        $this->assertFalse($this->service->wouldExceed($taxNumber, $invoiceNumber, 5.00));
    }

    public function test_reserve_capacity_allows_callback_when_within_limit(): void
    {
        $taxNumber = 'TAX-2001';
        $invoiceNumber = 'INV-2001';

        $this->makeFinancingRequest($this->bank1, $this->creator1, $taxNumber, $invoiceNumber, 35.00, RequestStatus::SUBMITTED);

        $created = $this->service->reserveCapacity($taxNumber, $invoiceNumber, 20.00, function () use ($taxNumber, $invoiceNumber): ImportRequest {
            return $this->makeFinancingRequest($this->bank2, $this->creator2, $taxNumber, $invoiceNumber, 20.00, RequestStatus::DRAFT);
        });

        $this->assertInstanceOf(ImportRequest::class, $created);
        $this->assertSame(55.0, $this->service->usedPercent($taxNumber, $invoiceNumber));
    }

    public function test_reserve_capacity_throws_financing_limit_exceeded(): void
    {
        $taxNumber = 'TAX-2002';
        $invoiceNumber = 'INV-2002';

        $this->makeFinancingRequest($this->bank1, $this->creator1, $taxNumber, $invoiceNumber, 70.00, RequestStatus::SUBMITTED);

        $this->expectException(FinancingLimitExceededException::class);

        $this->service->reserveCapacity($taxNumber, $invoiceNumber, 40.00, function (): void {
            $this->fail('Callback must not run when financing limit is exceeded.');
        });
    }

    public function test_named_lock_is_released_after_financing_limit_exceeded(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL advisory lock assertions require a mysql connection.');
        }

        $taxNumber = 'TAX-LOCK-1';
        $invoiceNumber = 'INV-LOCK-1';

        $this->makeFinancingRequest($this->bank1, $this->creator1, $taxNumber, $invoiceNumber, 80.00, RequestStatus::SUBMITTED);

        try {
            $this->service->assertWithinLimit($taxNumber, $invoiceNumber, 30.00);
            $this->fail('Expected financing limit exception.');
        } catch (FinancingLimitExceededException) {
            // expected
        }

        $this->assertTrue($this->service->isNamedLockFree($taxNumber, $invoiceNumber));
    }

    public function test_named_lock_is_released_after_unexpected_exception(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL advisory lock assertions require a mysql connection.');
        }

        $taxNumber = 'TAX-LOCK-2';
        $invoiceNumber = 'INV-LOCK-2';

        try {
            $this->service->reserveCapacity($taxNumber, $invoiceNumber, 10.00, function (): void {
                throw new \RuntimeException('Simulated failure after validation.');
            });
            $this->fail('Expected simulated exception.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($this->service->isNamedLockFree($taxNumber, $invoiceNumber));
    }

    public function test_public_methods_return_scalar_values_only(): void
    {
        $reflection = new ReflectionClass(FinancingLedgerService::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || in_array($method->getName(), ['__construct', 'reserveCapacity'], true)) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType) {
                $this->fail(sprintf('Public method %s must declare a scalar return type.', $method->getName()));
            }

            $this->assertFalse(
                $returnType->getName() === ImportRequest::class,
                sprintf('Public method %s must not return ImportRequest rows.', $method->getName())
            );
            $this->assertFalse(
                str_contains($returnType->getName(), 'Collection'),
                sprintf('Public method %s must not return collections.', $method->getName())
            );
        }
    }

    public function test_concurrent_submissions_for_empty_invoice_key_cannot_jointly_exceed_one_hundred_percent(): void
    {
        $databasePath = $this->temporarySqliteDatabasePath();
        $environment = $this->parallelProcessEnvironment($databasePath);

        try {
            $this->runProcess(new Process([PHP_BINARY, 'artisan', 'migrate:fresh', '--force', '--no-interaction'], base_path(), $environment));

            $ids = json_decode($this->runProcess(new Process([PHP_BINARY, '-r', $this->parallelSetupScript()], base_path(), $environment)), true);
            $this->assertIsArray($ids);

            $taxNumber = 'TAX-PARALLEL';
            $invoiceNumber = 'INV-PARALLEL';

            $processes = collect(range(1, 2))->map(function (int $worker) use ($environment, $ids, $taxNumber, $invoiceNumber): Process {
                $bankId = $worker === 1 ? (int) $ids['bank1_id'] : (int) $ids['bank2_id'];
                $creatorId = $worker === 1 ? (int) $ids['creator1_id'] : (int) $ids['creator2_id'];

                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $this->parallelReserveScript(
                        $bankId,
                        $creatorId,
                        $taxNumber,
                        $invoiceNumber,
                    ),
                ], base_path(), $environment);
                $process->setTimeout(60);
                $process->start();

                return $process;
            });

            $results = $processes
                ->map(fn (Process $process): array => json_decode(trim($this->waitForProcess($process)), true))
                ->all();

            $accepted = collect($results)->where('accepted', true)->count();
            $rejected = collect($results)->where('accepted', false)->count();

            $this->assertSame(1, $accepted);
            $this->assertSame(1, $rejected);
            $this->assertSame('FINANCING_LIMIT_EXCEEDED', $results[0]['error_code'] ?? $results[1]['error_code']);
        } finally {
            if (is_file($databasePath)) {
                unlink($databasePath);
            }
        }
    }

    private function makeUser(UserRole $role, Bank $bank): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@financing.test",
            'password' => Hash::make('password'),
            'role' => $role,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    private function makeFinancingRequest(
        Bank $bank,
        User $creator,
        string $taxNumber,
        string $invoiceNumber,
        float $requestPercentage,
        RequestStatus $status,
    ): ImportRequest {
        app()->instance('workflow.transition.active', true);

        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
                'invoice_number' => $invoiceNumber,
                'trader_snapshot_tax_number' => $taxNumber,
                'request_percentage' => $requestPercentage,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function compositeIndexExists(): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('import_requests')"))
                ->pluck('name')
                ->contains('idx_trader_snapshot_tax_invoice');
        }

        return collect(DB::select('SHOW INDEX FROM import_requests'))
            ->pluck('Key_name')
            ->unique()
            ->contains('idx_trader_snapshot_tax_invoice');
    }

    private function temporarySqliteDatabasePath(): string
    {
        $directory = storage_path('framework/testing');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/financing-ledger-'.Str::uuid().'.sqlite';
        touch($path);

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function parallelProcessEnvironment(string $databasePath): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $databasePath,
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'MAIL_MAILER' => 'array',
        ];
    }

    private function parallelSetupScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 30000');
$bank1 = App\Models\Bank::query()->create(['name' => 'Bank One', 'code' => 'B1', 'is_active' => true]);
$bank2 = App\Models\Bank::query()->create(['name' => 'Bank Two', 'code' => 'B2', 'is_active' => true]);
$creator1 = App\Models\User::query()->create([
    'name' => 'Creator One',
    'email' => 'creator1@financing.test',
    'password' => Illuminate\Support\Facades\Hash::make('password'),
    'role' => App\Enums\UserRole::DATA_ENTRY->value,
    'bank_id' => $bank1->id,
    'is_active' => true,
]);
$creator2 = App\Models\User::query()->create([
    'name' => 'Creator Two',
    'email' => 'creator2@financing.test',
    'password' => Illuminate\Support\Facades\Hash::make('password'),
    'role' => App\Enums\UserRole::DATA_ENTRY->value,
    'bank_id' => $bank2->id,
    'is_active' => true,
]);
echo json_encode([
    'bank1_id' => $bank1->id,
    'bank2_id' => $bank2->id,
    'creator1_id' => $creator1->id,
    'creator2_id' => $creator2->id,
]);
PHP;
    }

    private function parallelReserveScript(
        int $bankId,
        int $creatorId,
        string $taxNumber,
        string $invoiceNumber,
    ): string {
        return <<<PHP
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 30000');
\$service = app(App\Services\FinancingLedgerService::class);
\$accepted = false;
\$errorCode = null;
try {
    \$service->reserveCapacity('{$taxNumber}', '{$invoiceNumber}', 60.00, function () use (\$service): void {
        app()->instance('workflow.transition.active', true);
        try {
            App\Models\ImportRequest::query()->create([
                'bank_id' => {$bankId},
                'created_by' => {$creatorId},
                'currency' => 'USD',
                'amount' => 10000,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => App\Enums\RequestStatus::DRAFT,
                'current_owner_role' => App\Enums\UserRole::DATA_ENTRY,
                'invoice_number' => '{$invoiceNumber}',
                'trader_snapshot_tax_number' => '{$taxNumber}',
                'request_percentage' => 60.00,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
        usleep(200000);
    });
    \$accepted = true;
} catch (App\Exceptions\FinancingLimitExceededException \$exception) {
    \$errorCode = App\Exceptions\FinancingLimitExceededException::ERROR_CODE;
}
echo json_encode(['accepted' => \$accepted, 'error_code' => \$errorCode]);
PHP;
    }

    private function runProcess(Process $process): string
    {
        $process->setTimeout(60);
        $process->mustRun();

        return $process->getOutput();
    }

    private function waitForProcess(Process $process): string
    {
        $process->wait();

        if (! $process->isSuccessful()) {
            $this->fail($process->getErrorOutput() ?: $process->getOutput());
        }

        return $process->getOutput();
    }
}

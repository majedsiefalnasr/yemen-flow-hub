<?php

namespace Tests\Feature\Requests;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReferenceNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-07 10:00:00'));

        $this->bank = Bank::query()->create([
            'name' => 'Test Bank',
            'code' => 'TB',
            'is_active' => true,
        ]);

        $this->creator = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'data-entry@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reference_number_format_yearly_reset_and_soft_delete_continuity(): void
    {
        $first = $this->makeRequest();
        $second = $this->makeRequest();
        $second->delete();
        $third = $this->makeRequest();

        $this->assertSame('YFH-2026-000001', $first->reference_number);
        $this->assertSame('YFH-2026-000002', $second->reference_number);
        $this->assertSame('YFH-2026-000003', $third->reference_number);
        $this->assertMatchesRegularExpression('/^YFH-2026-\d{6}$/', $third->reference_number);

        Carbon::setTestNow(Carbon::parse('2027-01-01 10:00:00'));
        $nextYear = $this->makeRequest();

        $this->assertSame('YFH-2027-000001', $nextYear->reference_number);
    }

    public function test_explicit_reference_number_is_preserved_and_keeps_future_generation_continuous(): void
    {
        $manual = $this->makeRequest(['reference_number' => 'YFH-2026-000100']);
        $generated = $this->makeRequest();

        $this->assertSame('YFH-2026-000100', $manual->reference_number);
        $this->assertSame('YFH-2026-000101', $generated->reference_number);
    }

    public function test_repeated_same_year_preserves_unique_contiguous_reference_numbers(): void
    {
        $requests = collect(range(1, 25))->map(fn (): ImportRequest => $this->makeRequest());
        $referenceNumbers = $requests->pluck('reference_number')->all();

        $this->assertCount(25, array_unique($referenceNumbers));
        $this->assertSame('YFH-2026-000001', $referenceNumbers[0]);
        $this->assertSame('YFH-2026-000025', $referenceNumbers[24]);
        $this->assertSame(25, DB::table('import_request_reference_sequences')->where('year', '2026')->value('last_value'));
    }

    public function test_parallel_creates_reserve_unique_reference_numbers(): void
    {
        $databasePath = $this->temporarySqliteDatabasePath();
        $environment = $this->parallelProcessEnvironment($databasePath);

        try {
            $this->runProcess(new Process([PHP_BINARY, 'artisan', 'migrate:fresh', '--force', '--no-interaction'], base_path(), $environment));

            $ids = json_decode($this->runProcess(new Process([PHP_BINARY, '-r', $this->parallelSetupScript()], base_path(), $environment)), true);
            $this->assertIsArray($ids);

            $processes = collect(range(1, 4))->map(function () use ($environment, $ids): Process {
                $process = new Process([PHP_BINARY, '-r', $this->parallelCreateScript((int) $ids['bank_id'], (int) $ids['user_id'])], base_path(), $environment);
                $process->setTimeout(60);
                $process->start();

                return $process;
            });

            $referenceNumbers = $processes->map(fn (Process $process): string => trim($this->waitForProcess($process)))->all();
            sort($referenceNumbers);

            $this->assertCount(4, array_unique($referenceNumbers));
            $this->assertSame('YFH-2026-000001', $referenceNumbers[0]);
            $this->assertSame('YFH-2026-000004', $referenceNumbers[3]);
        } finally {
            if (is_file($databasePath)) {
                unlink($databasePath);
            }
        }
    }

    public function test_seeded_sequence_table_drives_generation(): void
    {
        DB::table('import_request_reference_sequences')->insert([
            'year' => '2026',
            'last_value' => 41,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = $this->makeRequest();
        $second = $this->makeRequest();

        $this->assertSame('YFH-2026-000042', $first->reference_number);
        $this->assertSame('YFH-2026-000043', $second->reference_number);
    }

    private function makeRequest(array $attributes = []): ImportRequest
    {
        app()->instance('workflow.transition.active', true);

        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'created_by' => $this->creator->id,
                'currency' => 'USD',
                'amount' => 5000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
                ...$attributes,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function temporarySqliteDatabasePath(): string
    {
        $directory = storage_path('framework/testing');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/reference-number-'.Str::uuid().'.sqlite';
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
            'CACHE_STORE' => 'array',
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
Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-06-07 10:00:00'));
$bank = App\Models\Bank::query()->create(['name' => 'Parallel Bank', 'code' => 'PB', 'is_active' => true]);
$user = App\Models\User::query()->create([
    'name' => 'Parallel Data Entry',
    'email' => 'parallel-data-entry@example.com',
    'password' => Illuminate\Support\Facades\Hash::make('password'),
    'role' => App\Enums\UserRole::DATA_ENTRY->value,
    'bank_id' => $bank->id,
    'is_active' => true,
]);
Illuminate\Support\Facades\DB::table('import_request_reference_sequences')->insert([
    'year' => '2026',
    'last_value' => 0,
    'created_at' => now(),
    'updated_at' => now(),
]);
echo json_encode(['bank_id' => $bank->id, 'user_id' => $user->id]);
PHP;
    }

    private function parallelCreateScript(int $bankId, int $userId): string
    {
        return <<<PHP
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 30000');
Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-06-07 10:00:00'));
app()->instance('workflow.transition.active', true);
try {
    \$request = null;

    for (\$attempt = 1; \$attempt <= 50; \$attempt++) {
        try {
            \$request = App\Models\ImportRequest::query()->create([
                'bank_id' => {$bankId},
                'created_by' => {$userId},
                'currency' => 'USD',
                'amount' => 5000.00,
                'supplier_name' => 'Parallel Supplier',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'status' => App\Enums\RequestStatus::DRAFT,
                'current_owner_role' => App\Enums\UserRole::DATA_ENTRY,
            ]);

            break;
        } catch (Illuminate\Database\QueryException \$exception) {
            if (! str_contains(\$exception->getMessage(), 'database is locked') || \$attempt === 50) {
                throw \$exception;
            }

            usleep(random_int(50_000, 250_000));
            Illuminate\Support\Facades\DB::purge();
            Illuminate\Support\Facades\DB::reconnect();
            Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL');
            Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 30000');
        }
    }
} finally {
    app()->offsetUnset('workflow.transition.active');
}
echo \$request->reference_number;
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

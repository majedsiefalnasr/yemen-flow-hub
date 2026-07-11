<?php

namespace App\Console\Commands;

use App\Exceptions\EngineException;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\EngineRequestService;
use App\Support\QueryMetrics;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Audit remediation load-run harness (roadmap Phase A "establish the
 * load-test harness"). Bulk-inserts a self-contained, tagged dataset
 * directly into the real (MySQL) connection this process is configured
 * against -- NOT the SQLite test suite, since the p95/EXPLAIN gates are
 * about MySQL query plans at scale, which SQLite does not represent.
 *
 * Every row this command creates carries the same reference prefix
 * (PERF-LOAD-) and is deleted at the end regardless of success/failure,
 * so a run against a shared dev database is reversible.
 *
 * Usage: php artisan perf:load-scenario --rows=200000
 */
class PerfLoadScenarioCommand extends Command
{
    protected $signature = 'perf:load-scenario {--rows=200000} {--keep : do not delete the seeded rows at the end} {--cleanup-only : delete any leftover PERF-LOAD-* fixture data and exit (recovery path if a prior run crashed before its own cleanup ran, e.g. an out-of-memory fatal that skips finally)} {--concurrency=0 : instead of the p95 seed run, fork this many worker processes that each create requests in parallel through EngineRequestService::create() to load-test the reference allocator under true contention (API-003)} {--creates-per-worker=25 : with --concurrency, how many real creates each worker performs}';

    protected $description = 'Bulk-seed engine_requests and measure my-queue / list endpoint p95 + query counts (audit remediation load-run harness)';

    private const REF_PREFIX = 'PERF-LOAD-';

    public function handle(QueryMetrics $queryMetrics): int
    {
        // Bulk-inserting 100k+ rows from CLI needs headroom beyond the
        // default 128M memory_limit (Eloquent/query overhead accumulates
        // across chunks); this only affects this process, not the app.
        ini_set('memory_limit', '1024M');
        DB::connection()->disableQueryLog();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('This command must run against the mysql connection (DB_CONNECTION=mysql), not sqlite -- the gates being measured are MySQL query-plan gates.');

            return self::FAILURE;
        }

        if ($this->option('cleanup-only')) {
            $this->info('Cleaning up any leftover PERF-LOAD-* fixture data…');
            $this->cleanup();
            $this->info('Cleanup done.');

            return self::SUCCESS;
        }

        $concurrency = (int) $this->option('concurrency');
        if ($concurrency > 0) {
            return $this->runConcurrencyScenario($concurrency, (int) $this->option('creates-per-worker'));
        }

        $rows = (int) $this->option('rows');
        $keep = (bool) $this->option('keep');

        // Defensive: a prior run's fatal OOM crash skips its own finally
        // cleanup (there's no memory left to run it), so always clear any
        // leftover PERF-LOAD-* fixture before building a fresh one.
        $this->cleanup();

        $this->info('Setting up fixture (bank, workflow, stages, permissions, user)…');
        $fixture = $this->buildFixture();

        try {
            $this->info('Bulk-inserting '.$rows.' engine_requests rows tagged '.self::REF_PREFIX.'…');
            $this->bulkInsert($fixture, $rows);

            $actualCount = DB::table('engine_requests')->where('reference', 'like', self::REF_PREFIX.'%')->count();
            $this->info("Seeded {$actualCount} rows.");

            $this->newLine();
            $this->info('=== my-queue (DB-001 gate: p95 <= 300ms, 2 accessible stages) — 20 runs ===');
            $this->measureEndpointRepeated($queryMetrics, $fixture['user'], 'GET', '/api/v1/engine-requests/my-queue', [], 20);

            $this->newLine();
            $this->info('=== engine-requests list (DB-002/ARCH-004 gate: p95 <= 300ms, 2 accessible stages) — 20 runs ===');
            $this->measureEndpointRepeated($queryMetrics, $fixture['user'], 'GET', '/api/v1/engine-requests', ['from' => now()->subDays(30)->toDateString()], 20);

            $this->newLine();
            $this->info('=== API-001 gate: query count constant across page sizes (my-queue) ===');
            $this->measurePageSizeSeries($queryMetrics, $fixture['user']);

            $this->newLine();
            $this->info('=== reports/summary (API-002/API-005 gate: one grouped query, cold-cache first call only) ===');
            $this->info('  Note: CACHE-001 wraps this endpoint, so only the FIRST call actually runs the query; repeats are cache hits by design, not measured here.');
            $this->measureEndpointOnce($queryMetrics, $fixture['user'], 'GET', '/api/v1/reports/summary');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Load run failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        } finally {
            if (! $keep) {
                $this->info('Cleaning up seeded rows…');
                $this->cleanup();
                $this->info('Cleanup done.');
            } else {
                $this->warn('--keep passed: seeded rows left in place. Re-run with no flags to clean them up, or run cleanup manually:');
                $this->line("  DB::table('engine_requests')->where('reference', 'like', '".self::REF_PREFIX."%')->delete();");
            }
        }
    }

    /**
     * @return array{bank: Bank, stages: array{WorkflowStage, WorkflowStage}, version: WorkflowVersion, user: User, merchant: Merchant}
     */
    private function buildFixture(): array
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();

        $bank = Bank::create([
            'name' => 'Perf Load Bank',
            'code' => 'PERFLOAD',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        // bank_admin (not intake): needs reports.VIEW for the reports/summary
        // measurement below; intake deliberately has no reports capability.
        $role = Role::where('code', 'bank_admin')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $user = User::create([
            'name' => 'Perf Load User',
            'email' => 'perf-load-'.Str::random(8).'@perf.test',
            'password' => bcrypt('password'),
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $user->teams()->attach($team);
        $user->roles()->attach($role);

        $merchant = Merchant::create([
            'bank_id' => $bank->id,
            'name' => 'Perf Load Merchant',
            'tax_number' => 'PERF-TAX-'.Str::random(6),
            'status' => 'ACTIVE',
        ]);

        $def = WorkflowDefinition::create(['code' => 'PERF_LOAD_WF_'.Str::random(6), 'name' => 'Perf Load WF', 'is_active' => true]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $startStage = WorkflowStage::create([
            'workflow_version_id' => $version->id, 'code' => 'START', 'name' => 'Start',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
        ]);
        // DB-001/DB-002: two EXECUTE/VIEW-accessible stages, not one -- a
        // single accessible stage makes the my-queue/list whereIn(...)
        // degenerate to a single-value IN, which already uses the index
        // cleanly and never exercises the multi-stage filesort regression
        // these gates are about (docs/audit/evidence/DB-001-002-sla-deadline-column-followup.md).
        $execStageA = WorkflowStage::create([
            'workflow_version_id' => $version->id, 'code' => 'EXEC_A', 'name' => 'Exec A',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false,
            'sla_duration_minutes' => 1440, 'version' => 1,
        ]);
        $execStageB = WorkflowStage::create([
            'workflow_version_id' => $version->id, 'code' => 'EXEC_B', 'name' => 'Exec B',
            'sort_order' => 3, 'is_initial' => false, 'is_final' => false,
            'sla_duration_minutes' => 720, 'version' => 1,
        ]);

        foreach ([$execStageA, $execStageB] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => 'EXECUTE', 'display_label' => 'Exec', 'version' => 1,
            ]);
        }

        $submit = WorkflowAction::create(['code' => 'SUBMIT_PERF', 'name' => 'Submit', 'kind' => 'APPROVE', 'is_active' => true, 'version' => 1]);
        WorkflowTransition::create([
            'workflow_version_id' => $version->id, 'from_stage_id' => $startStage->id,
            'to_stage_id' => $execStageA->id, 'action_id' => $submit->id, 'requires_comment' => false, 'version' => 1,
        ]);

        return ['bank' => $bank, 'stages' => [$execStageA, $execStageB], 'version' => $version, 'user' => $user, 'merchant' => $merchant];
    }

    /**
     * @param  array{bank: Bank, stages: array{WorkflowStage, WorkflowStage}, version: WorkflowVersion, user: User, merchant: Merchant}  $fixture
     */
    private function bulkInsert(array $fixture, int $totalRows): void
    {
        $chunkSize = 2000;
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        $inserted = 0;
        [$stageA, $stageB] = $fixture['stages'];

        while ($inserted < $totalRows) {
            $thisChunk = min($chunkSize, $totalRows - $inserted);
            $rows = [];
            $now = now();

            for ($i = 0; $i < $thisChunk; $i++) {
                $seq = $inserted + $i;
                $stage = $seq % 2 === 0 ? $stageA : $stageB;
                $slaMinutes = $stage->sla_duration_minutes;
                $daysAgo = $seq % 400;
                $enteredAt = $now->copy()->subDays($daysAgo)->subMinutes($seq % 1440);
                $slaDeadlineEpoch = $slaMinutes !== null
                    ? $enteredAt->getTimestamp() + ($slaMinutes * 60)
                    : null;

                $rows[] = [
                    'workflow_version_id' => $fixture['version']->id,
                    'current_stage_id' => $stage->id,
                    'stage_entered_at' => $enteredAt,
                    'sla_deadline_epoch' => $slaDeadlineEpoch,
                    'reference' => self::REF_PREFIX.$seq,
                    'status' => 'ACTIVE',
                    'created_by' => $fixture['user']->id,
                    'bank_id' => $fixture['bank']->id,
                    'merchant_id' => $fixture['merchant']->id,
                    'data' => json_encode(['amount' => 1000 + $seq, 'currency' => 'USD']),
                    'version' => 1,
                    'amount' => 1000 + $seq,
                    'currency' => 'USD',
                    'invoice_number' => 'INV-'.$seq,
                    'invoice_number_normalized' => 'INV-'.$seq,
                    'created_at' => $enteredAt,
                    'updated_at' => $enteredAt,
                ];
            }

            DB::table('engine_requests')->insert($rows);
            $inserted += $thisChunk;
            $bar->advance($thisChunk);
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, elapsedMs: float, queryCount: int, queryTimeMs: float, body: string}
     */
    private function callOnce(QueryMetrics $queryMetrics, User $user, string $method, string $uri, array $query): array
    {
        Auth::guard('sanctum')->setUser($user);
        Auth::setUser($user);

        $queryMetrics->reset();
        $start = microtime(true);

        $request = HttpRequest::create($uri, $method, $query);
        $request->setUserResolver(fn () => $user);

        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);

        $elapsedMs = round((microtime(true) - $start) * 1000, 2);
        $status = $response->getStatusCode();
        $queryCount = $queryMetrics->count();
        $queryTimeMs = $queryMetrics->totalTimeMs();
        $body = $response->getContent() ?: '';

        $kernel->terminate($request, $response);

        return compact('status', 'elapsedMs', 'queryCount', 'queryTimeMs', 'body');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function measureEndpointOnce(QueryMetrics $queryMetrics, User $user, string $method, string $uri, array $query = []): void
    {
        $result = $this->callOnce($queryMetrics, $user, $method, $uri, $query);

        $this->line("  status: {$result['status']}");
        $this->line("  wall clock: {$result['elapsedMs']} ms");
        $this->line("  query count: {$result['queryCount']}");
        $this->line("  query time (sum): {$result['queryTimeMs']} ms");

        if ($result['status'] >= 400) {
            $this->warn('  response body: '.substr($result['body'], 0, 500));
        }
    }

    /**
     * Runs the same request $runs times and reports min/median/p95/max wall
     * clock plus the query count (which should be identical run-to-run for a
     * non-cached endpoint — flagged if it isn't).
     *
     * @param  array<string, mixed>  $query
     */
    private function measureEndpointRepeated(QueryMetrics $queryMetrics, User $user, string $method, string $uri, array $query, int $runs): void
    {
        // Warm-up call: PermissionService::userHasCapability() caches per role
        // for an hour, so the very first call in the process pays for a few
        // extra permission-resolution queries that every subsequent call
        // within this run will not — excluded from the measured series so it
        // doesn't skew the query-count-should-be-constant check below.
        $this->callOnce($queryMetrics, $user, $method, $uri, $query);

        $timings = [];
        $queryCounts = [];
        $lastStatus = 0;
        $lastBody = '';

        for ($i = 0; $i < $runs; $i++) {
            $result = $this->callOnce($queryMetrics, $user, $method, $uri, $query);
            $timings[] = $result['elapsedMs'];
            $queryCounts[] = $result['queryCount'];
            $lastStatus = $result['status'];
            $lastBody = $result['body'];
        }

        if ($lastStatus >= 400) {
            $this->warn("  status: {$lastStatus} on last run — response body: ".substr($lastBody, 0, 500));

            return;
        }

        sort($timings);
        $n = count($timings);
        $p95Index = (int) ceil(0.95 * $n) - 1;
        $median = $timings[(int) floor($n / 2)];
        $p95 = $timings[max(0, min($n - 1, $p95Index))];
        $min = $timings[0];
        $max = $timings[$n - 1];
        $distinctQueryCounts = array_unique($queryCounts);

        $this->line("  status: {$lastStatus} ({$n} runs)");
        $this->line("  wall clock — min: {$min} ms · median: {$median} ms · p95: {$p95} ms · max: {$max} ms");
        $this->line('  query count — '.(count($distinctQueryCounts) === 1
            ? "constant at {$distinctQueryCounts[0]}"
            : 'VARIED across runs: '.implode(', ', $distinctQueryCounts)));

        $gateStatus = $p95 <= 300 ? 'PASS' : 'FAIL';
        $this->line("  gate (p95 <= 300ms): {$gateStatus}");
    }

    /**
     * API-001: query count per list page must be constant regardless of page
     * size. Calls my-queue at three different per_page values and compares
     * the resulting query counts.
     */
    private function measurePageSizeSeries(QueryMetrics $queryMetrics, User $user): void
    {
        $pageSizes = [10, 50, 200];
        $counts = [];

        foreach ($pageSizes as $perPage) {
            $result = $this->callOnce($queryMetrics, $user, 'GET', '/api/v1/engine-requests/my-queue', ['per_page' => $perPage]);
            $counts[$perPage] = $result['queryCount'];

            if ($result['status'] >= 400) {
                $this->warn("  per_page={$perPage}: status {$result['status']} — ".substr($result['body'], 0, 300));

                continue;
            }

            $this->line("  per_page={$perPage}: query count = {$result['queryCount']}, wall clock = {$result['elapsedMs']} ms");
        }

        $distinct = array_unique($counts);
        $gateStatus = count($distinct) === 1 ? 'PASS' : 'FAIL';
        $this->line("  gate (query count constant across page sizes): {$gateStatus}");
    }

    /**
     * API-003 residual: load-test the reference allocator under TRUE
     * concurrency. Forks $workers real OS processes that each call the
     * production EngineRequestService::create() path $perWorker times,
     * all racing on the same MAX(CAST(numeric suffix AS UNSIGNED))+1
     * sequence against the same MySQL row-space. The SQLite test suite
     * cannot reproduce this (single-writer), which is why this lives in
     * the mysql-only harness rather than a PHPUnit test.
     *
     * Gate: every create must yield a distinct reference, with zero
     * REFERENCE_ALLOCATION_FAILED — i.e. the unique-constraint retry loop
     * must absorb every lost race within its 5-attempt budget.
     */
    private function runConcurrencyScenario(int $workers, int $perWorker): int
    {
        if (! function_exists('pcntl_fork')) {
            $this->error('The pcntl extension is required for the concurrency scenario (pcntl_fork not available).');

            return self::FAILURE;
        }

        $this->info("=== API-003 reference-allocator contention: {$workers} parallel workers × {$perWorker} creates each ===");
        $this->info('Setting up fixture (bank, workflow, initial stage, permission, user)…');
        $fixture = $this->buildFixture();

        // buildFixture() grants EXECUTE only on the two non-initial stages
        // (the p95 gates need exactly two accessible stages). Creating a
        // request enters the INITIAL stage, so this scenario additionally
        // needs EXECUTE on that stage — added here, not in the shared
        // fixture, so the p95 run's accessible-stage count is untouched.
        $initialStage = $fixture['version']->stages()->where('is_initial', true)->firstOrFail();
        StagePermission::create([
            'stage_id' => $initialStage->id,
            'organization_id' => $fixture['bank']->organization_id,
            'role_id' => Role::where('code', 'bank_admin')->firstOrFail()->id,
            'access_level' => 'EXECUTE',
            'display_label' => 'Intake',
            'version' => 1,
        ]);

        // Each worker writes its outcome as one JSON line to a shared temp
        // file (children cannot return data to the parent in-process; a file
        // is the simplest robust IPC and is unlinked in the finally block).
        $resultFile = tempnam(sys_get_temp_dir(), 'perf-ref-contention-');

        try {
            $expectedTotal = $workers * $perWorker;
            $this->info("Forking {$workers} workers; expecting {$expectedTotal} unique references…");

            $pids = [];
            for ($w = 0; $w < $workers; $w++) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    $this->error('pcntl_fork failed.');

                    return self::FAILURE;
                }

                if ($pid === 0) {
                    // Child: run the worker and exit. Never returns.
                    $this->runContentionWorker($fixture, $perWorker, $resultFile);
                    exit(0);
                }

                $pids[] = $pid;
            }

            // Parent: wait for every child to finish.
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
            }

            return $this->reportContentionResults($fixture, $resultFile, $expectedTotal);
        } catch (Throwable $e) {
            $this->error('Concurrency scenario failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        } finally {
            @unlink($resultFile);
            $this->info('Cleaning up seeded rows and fixture…');
            $this->cleanup();
            $this->info('Cleanup done.');
        }
    }

    /**
     * Runs inside a forked child. Reconnects the DB (an inherited MySQL
     * socket is not safe to share across a fork), performs $perWorker real
     * creates through the production service, and appends its outcome to the
     * shared result file.
     *
     * @param  array{bank: Bank, stages: array{WorkflowStage, WorkflowStage}, version: WorkflowVersion, user: User, merchant: Merchant}  $fixture
     */
    private function runContentionWorker(array $fixture, int $perWorker, string $resultFile): void
    {
        // A forked process inherits the parent's open MySQL connection socket;
        // sharing it corrupts the protocol. Drop it so this child opens its own.
        DB::purge();

        $service = app(EngineRequestService::class);
        $user = User::findOrFail($fixture['user']->id);
        $version = WorkflowVersion::findOrFail($fixture['version']->id);

        $created = 0;
        $allocationFailures = 0;
        $otherErrors = [];

        for ($i = 0; $i < $perWorker; $i++) {
            try {
                $service->create($version, [
                    'merchant_id' => $fixture['merchant']->id,
                    'data' => ['amount' => 100, 'currency' => 'USD'],
                ], $user);
                $created++;
            } catch (EngineException $e) {
                if ($e->getErrorCode() === 'REFERENCE_ALLOCATION_FAILED') {
                    $allocationFailures++;
                } else {
                    $otherErrors[] = $e->getErrorCode().': '.$e->getMessage();
                }
            } catch (Throwable $e) {
                $otherErrors[] = get_class($e).': '.$e->getMessage();
            }
        }

        $line = json_encode([
            'pid' => getmypid(),
            'created' => $created,
            'allocationFailures' => $allocationFailures,
            'otherErrors' => $otherErrors,
        ]).PHP_EOL;

        file_put_contents($resultFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Parent-side aggregation and gate verdict for the contention scenario.
     *
     * @param  array{bank: Bank, stages: array{WorkflowStage, WorkflowStage}, version: WorkflowVersion, user: User, merchant: Merchant}  $fixture
     */
    private function reportContentionResults(array $fixture, string $resultFile, int $expectedTotal): int
    {
        $lines = array_filter(explode(PHP_EOL, (string) file_get_contents($resultFile)));

        $reportedCreated = 0;
        $allocationFailures = 0;
        $otherErrors = [];

        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }
            $reportedCreated += (int) ($row['created'] ?? 0);
            $allocationFailures += (int) ($row['allocationFailures'] ?? 0);
            $otherErrors = array_merge($otherErrors, (array) ($row['otherErrors'] ?? []));
        }

        // Ground truth: count what actually landed in the table for this
        // fixture's bank, and how many of those references are distinct.
        $bankId = $fixture['bank']->id;
        $actualRows = DB::table('engine_requests')->where('bank_id', $bankId)->count();
        $distinctRefs = DB::table('engine_requests')->where('bank_id', $bankId)->distinct()->count('reference');

        $this->newLine();
        $this->line("  workers reported created: {$reportedCreated} (expected {$expectedTotal})");
        $this->line("  rows actually in table:   {$actualRows}");
        $this->line("  distinct references:      {$distinctRefs}");
        $this->line("  REFERENCE_ALLOCATION_FAILED: {$allocationFailures}");

        if ($otherErrors !== []) {
            $this->warn('  other errors ('.count($otherErrors).'):');
            foreach (array_slice($otherErrors, 0, 10) as $err) {
                $this->warn('    - '.$err);
            }
        }

        $uniquenessHeld = $actualRows === $distinctRefs;
        $noAllocFailure = $allocationFailures === 0;
        $allLanded = $actualRows === $expectedTotal && $reportedCreated === $expectedTotal;

        $this->newLine();
        $this->line('  gate — every reference distinct (no duplicate escaped): '.($uniquenessHeld ? 'PASS' : 'FAIL'));
        $this->line('  gate — zero REFERENCE_ALLOCATION_FAILED:               '.($noAllocFailure ? 'PASS' : 'FAIL'));
        $this->line('  gate — every create landed exactly once:               '.($allLanded ? 'PASS' : 'FAIL'));

        $passed = $uniquenessHeld && $noAllocFailure && $allLanded && $otherErrors === [];
        $this->newLine();
        $this->info($passed ? '  API-003 contention gate: PASS' : '  API-003 contention gate: FAIL');

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function cleanup(): void
    {
        DB::table('engine_requests')->where('reference', 'like', self::REF_PREFIX.'%')->delete();

        $bank = Bank::where('code', 'PERFLOAD')->first();
        if ($bank !== null) {
            // The concurrency scenario creates real requests through the service
            // (ENG-* references, not PERF-LOAD-*) plus their workflow_history
            // rows. Remove every request for this fixture bank — and its history
            // first — so the stage delete below is not blocked by an FK. Scoped
            // to the PERFLOAD bank only, so real data in other banks is untouched.
            $fixtureRequestIds = DB::table('engine_requests')->where('bank_id', $bank->id)->pluck('id');
            if ($fixtureRequestIds->isNotEmpty()) {
                DB::table('workflow_history')->whereIn('request_id', $fixtureRequestIds)->delete();
                DB::table('engine_requests')->whereIn('id', $fixtureRequestIds)->delete();
            }

            $version = WorkflowVersion::whereHas('definition', fn ($q) => $q->where('code', 'like', 'PERF_LOAD_WF_%'))->first();
            if ($version !== null) {
                WorkflowTransition::where('workflow_version_id', $version->id)->delete();
                StagePermission::whereIn('stage_id', WorkflowStage::where('workflow_version_id', $version->id)->pluck('id'))->delete();
                WorkflowStage::where('workflow_version_id', $version->id)->delete();
                $defId = $version->workflow_definition_id;
                $version->delete();
                WorkflowDefinition::where('id', $defId)->delete();
            }
            WorkflowAction::where('code', 'SUBMIT_PERF')->delete();
            Merchant::where('bank_id', $bank->id)->delete();
            User::where('bank_id', $bank->id)->where('email', 'like', 'perf-load-%')->delete();
            $bank->delete();
        }
    }
}

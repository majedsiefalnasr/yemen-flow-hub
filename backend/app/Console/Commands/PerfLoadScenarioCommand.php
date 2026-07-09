<?php

namespace App\Console\Commands;

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
    protected $signature = 'perf:load-scenario {--rows=200000} {--keep : do not delete the seeded rows at the end} {--cleanup-only : delete any leftover PERF-LOAD-* fixture data and exit (recovery path if a prior run crashed before its own cleanup ran, e.g. an out-of-memory fatal that skips finally)}';

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
            $this->info('=== my-queue (DB-001 gate: p95 <= 300ms) — 20 runs ===');
            $this->measureEndpointRepeated($queryMetrics, $fixture['user'], 'GET', '/api/v1/engine-requests/my-queue', [], 20);

            $this->newLine();
            $this->info('=== engine-requests list (DB-002/ARCH-004 gate: p95 <= 300ms) — 20 runs ===');
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
     * @return array{bank: Bank, stage: WorkflowStage, version: WorkflowVersion, user: User}
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
        $execStage = WorkflowStage::create([
            'workflow_version_id' => $version->id, 'code' => 'EXEC', 'name' => 'Exec',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false,
            'sla_duration_minutes' => 1440, 'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $execStage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
            'access_level' => 'EXECUTE', 'display_label' => 'Exec', 'version' => 1,
        ]);

        $submit = WorkflowAction::create(['code' => 'SUBMIT_PERF', 'name' => 'Submit', 'kind' => 'APPROVE', 'is_active' => true, 'version' => 1]);
        WorkflowTransition::create([
            'workflow_version_id' => $version->id, 'from_stage_id' => $startStage->id,
            'to_stage_id' => $execStage->id, 'action_id' => $submit->id, 'requires_comment' => false, 'version' => 1,
        ]);

        return ['bank' => $bank, 'stage' => $execStage, 'version' => $version, 'user' => $user, 'merchant' => $merchant];
    }

    /**
     * @param  array{bank: Bank, stage: WorkflowStage, version: WorkflowVersion, user: User, merchant: Merchant}  $fixture
     */
    private function bulkInsert(array $fixture, int $totalRows): void
    {
        $chunkSize = 2000;
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        $inserted = 0;
        while ($inserted < $totalRows) {
            $thisChunk = min($chunkSize, $totalRows - $inserted);
            $rows = [];
            $now = now();

            for ($i = 0; $i < $thisChunk; $i++) {
                $seq = $inserted + $i;
                $daysAgo = $seq % 400;
                $enteredAt = $now->copy()->subDays($daysAgo)->subMinutes($seq % 1440);

                $rows[] = [
                    'workflow_version_id' => $fixture['version']->id,
                    'current_stage_id' => $fixture['stage']->id,
                    'stage_entered_at' => $enteredAt,
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

    private function cleanup(): void
    {
        DB::table('engine_requests')->where('reference', 'like', self::REF_PREFIX.'%')->delete();

        $bank = Bank::where('code', 'PERFLOAD')->first();
        if ($bank !== null) {
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

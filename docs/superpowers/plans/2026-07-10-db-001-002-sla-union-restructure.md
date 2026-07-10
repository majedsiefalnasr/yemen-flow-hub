# DB-001 / DB-002 UNION-per-stage Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close roadmap gates DB-001 and DB-002 by restructuring `my-queue` and the engine-request list endpoint's queries so a user with access to multiple workflow stages no longer forces MySQL into a full-table filesort.

**Architecture:** New `UnionStagePaginator` support class builds one filtered, sorted, limited subquery per accessible stage ID, combines them via `unionAll()`, and re-sorts/re-slices the small merged result to resolve a page of IDs — then hydrates real Eloquent models for those IDs. Falls back to today's `whereIn(...)` query when the accessible-stage count exceeds a configurable threshold. Both `EngineRequestController::myQueue()` and `::index()` call the same paginator with different sort specs.

**Tech Stack:** Laravel 11 query builder (`unionAll`, `fromSub`), Eloquent, PHPUnit, MySQL (production/dev), SQLite (test suite).

## Global Constraints

- Response ordering and pagination `meta` (`current_page`, `last_page`, `per_page`, `total`) must be byte-identical to the current `whereIn(...)` path for every case the new path handles — this is a performance fix, not a behavior change.
- `EngineRequestListQuery::applyFilters()` must not change — it is called unchanged per stage branch.
- New paginator must work on both MySQL (production/dev) and SQLite (test suite) — no MySQL-only SQL functions.
- Threshold above which the code falls back to the old `whereIn` query is `config('workflow.list_union_stage_threshold', 10)`, not hardcoded.
- Zero accessible stage IDs must short-circuit to an empty paginator with no query, exactly as today.
- All commits signed, conventional-commit format, scope `backend`, co-authored by `Claude <noreply@anthropic.com>`.
- Backend focused verification per task: `php artisan test --filter=<TestClass>` and `vendor/bin/pint <touched files> --test`. No full suite run until the final task.

---

### Task 1: `UnionStagePaginator` — core class, single-stage and empty-stage-list behavior

**Files:**
- Create: `backend/app/Support/UnionStagePaginator.php`
- Test: `backend/tests/Feature/Engine/UnionStagePaginatorTest.php`

**Interfaces:**
- Produces: `App\Support\UnionStagePaginator::paginate(Closure $branchFactory, array $stageIds, array $sortSpec, int $page, int $perPage, ?int $threshold = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator`
  - `$branchFactory`: `fn(int $stageId): \Illuminate\Database\Eloquent\Builder` — returns a fully-filtered, `withStageEntry()`-joined, `EngineRequestListQuery::applyFilters()`-applied query scoped to exactly that one stage (`where('engine_requests.current_stage_id', $stageId)`), with no `orderBy`/`limit`/`select` applied yet.
  - `$sortSpec`: `list<array{0: string, 1: 'asc'|'desc'}>` — e.g. `[['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']]`. Each entry's first element must be a raw column name (no `orderByRaw` expressions — the CASE-based null-first tiebreaker from `orderBySlaPriority()` is handled separately in Task 3, not via this generic spec).
  - `$threshold`: stage-count ceiling above which the old `whereIn` query is used instead. Defaults to `config('workflow.list_union_stage_threshold', 10)` when null.
  - Consumed by `EngineRequestController::myQueue()` and `::index()` (Tasks 3 and 4).

- [ ] **Step 1: Write the failing test for empty stage list**

```php
<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Support\EngineRequestListQuery;
use App\Support\UnionStagePaginator;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UnionStagePaginatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stageOne;

    private WorkflowStage $stageTwo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bank = Bank::create(['name' => 'Union Bank', 'code' => 'UNIONB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $role = Role::where('code', 'intake')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $this->user = User::create([
            'name' => 'Union User', 'email' => 'union@test.bank', 'password' => bcrypt('password'),
            'bank_id' => $this->bank->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->user->teams()->attach($team);
        $this->user->roles()->attach($role);

        $def = WorkflowDefinition::create(['code' => 'UNION_WF', 'name' => 'Union WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => 'PUBLISHED', 'published_at' => now(), 'version' => 1,
        ]);

        $this->stageOne = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'STAGE_ONE', 'name' => 'Stage One',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
        ]);
        $this->stageTwo = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'STAGE_TWO', 'name' => 'Stage Two',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'version' => 1,
        ]);

        foreach ([$this->stageOne, $this->stageTwo] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => 'EXECUTE', 'display_label' => 'Exec', 'version' => 1,
            ]);
        }
    }

    private function makeRequest(WorkflowStage $stage, string $reference, \DateTimeInterface $createdAt): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-'.$reference,
            'data' => [],
            'version' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function branchFactory(): \Closure
    {
        $listQuery = app(EngineRequestListQuery::class);
        $request = Request::create('/api/v1/engine-requests', 'GET');

        return function (int $stageId) use ($listQuery, $request): Builder {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->where('engine_requests.bank_id', $this->bank->id)
                ->where('engine_requests.current_stage_id', $stageId);
            $listQuery->applyFilters($query, $request);

            return $query;
        };
    }

    public function test_empty_stage_list_returns_empty_paginator_with_no_query(): void
    {
        $this->makeRequest($this->stageOne, 'ENG-EMPTY-001', now());

        $paginator = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $this->assertSame(0, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    public function test_single_stage_returns_rows_ordered_and_paginated_correctly(): void
    {
        $r1 = $this->makeRequest($this->stageOne, 'ENG-S1-001', now()->subDays(3));
        $r2 = $this->makeRequest($this->stageOne, 'ENG-S1-002', now()->subDays(1));
        $r3 = $this->makeRequest($this->stageOne, 'ENG-S1-003', now()->subDays(2));

        $paginator = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $this->assertSame(3, $paginator->total());
        $this->assertSame(
            [$r2->reference, $r3->reference, $r1->reference],
            collect($paginator->items())->pluck('reference')->all(),
        );
    }

    public function test_two_stages_merge_and_sort_correctly_across_branches(): void
    {
        $a = $this->makeRequest($this->stageOne, 'ENG-MRG-A', now()->subDays(1));
        $b = $this->makeRequest($this->stageTwo, 'ENG-MRG-B', now()->subDays(5));
        $c = $this->makeRequest($this->stageOne, 'ENG-MRG-C', now()->subDays(3));
        $d = $this->makeRequest($this->stageTwo, 'ENG-MRG-D', now());

        $paginator = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $this->assertSame(4, $paginator->total());
        $this->assertSame(
            [$d->reference, $a->reference, $c->reference, $b->reference],
            collect($paginator->items())->pluck('reference')->all(),
        );
    }

    public function test_page_two_returns_the_correct_slice_across_merged_branches(): void
    {
        $refs = [];
        foreach (range(1, 5) as $i) {
            $refs[] = $this->makeRequest($this->stageOne, "ENG-PAGE-{$i}", now()->subDays(10 - $i))->reference;
        }
        foreach (range(1, 5) as $i) {
            $refs[] = $this->makeRequest($this->stageTwo, "ENG-PAGE-T{$i}", now()->subDays(20 - $i))->reference;
        }

        $page1 = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 4,
        );
        $page2 = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 2,
            perPage: 4,
        );

        $this->assertSame(10, $page1->total());
        $this->assertSame(10, $page2->total());
        $this->assertCount(4, $page1->items());
        $this->assertCount(4, $page2->items());

        $page1Refs = collect($page1->items())->pluck('reference')->all();
        $page2Refs = collect($page2->items())->pluck('reference')->all();
        $this->assertEmpty(array_intersect($page1Refs, $page2Refs), 'page 1 and page 2 must not overlap');
    }

    public function test_stage_count_above_threshold_falls_back_to_whereIn_and_returns_correct_results(): void
    {
        $r1 = $this->makeRequest($this->stageOne, 'ENG-FALLBACK-1', now()->subDay());
        $r2 = $this->makeRequest($this->stageTwo, 'ENG-FALLBACK-2', now());

        $paginator = UnionStagePaginator::paginate(
            fn (int $stageId) => EngineRequest::query()
                ->withStageEntry()
                ->where('engine_requests.bank_id', $this->bank->id)
                ->where('engine_requests.current_stage_id', $stageId),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
            threshold: 1,
        );

        $this->assertSame(2, $paginator->total());
        $this->assertSame([$r2->reference, $r1->reference], collect($paginator->items())->pluck('reference')->all());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UnionStagePaginatorTest`
Expected: FAIL — `Class "App\Support\UnionStagePaginator" not found`

- [ ] **Step 3: Write the implementation**

The `whereIn` fallback works by filtering the first branch's `wheres` array down to everything *except* the stage-equality predicate, then adding a fresh `whereIn` — simpler and less fragile than rewriting a single `where` entry in place, since it only depends on the documented `Builder::getQuery()->wheres` shape (each entry has a `column` key) rather than reconstructing Laravel's internal `bindings` array by hand.

```php
<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * DB-001/DB-002: MySQL cannot use an index for both a multi-value
 * `current_stage_id IN (...)` filter and an ORDER BY on a different column
 * at the same time (confirmed via EXPLAIN — see
 * docs/audit/evidence/DB-001-002-sla-deadline-column-followup.md). This
 * splits a multi-stage query into one indexed, sorted, limited subquery per
 * stage, UNION ALLs them, and re-sorts/re-slices the small merged result —
 * the standard MySQL workaround for "IN + ORDER BY across a composite
 * index". Falls back to a single `whereIn` query above $threshold stages to
 * avoid issuing dozens of subqueries for a broad-access role.
 */
class UnionStagePaginator
{
    /**
     * @param  \Closure(int): Builder  $branchFactory  Returns a fully-filtered query scoped to exactly one stage ID via a single Basic where on engine_requests.current_stage_id, with no orderBy/limit/select applied.
     * @param  list<int>  $stageIds
     * @param  list<array{0: string, 1: 'asc'|'desc'}|array{0: 'raw', 1: string, 2: 'asc'|'desc'}>  $sortSpec
     */
    public static function paginate(
        \Closure $branchFactory,
        array $stageIds,
        array $sortSpec,
        int $page,
        int $perPage,
        ?int $threshold = null,
    ): LengthAwarePaginatorContract {
        if ($stageIds === []) {
            return new LengthAwarePaginator([], 0, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
        }

        $threshold ??= config('workflow.list_union_stage_threshold', 10);

        if (count($stageIds) > $threshold) {
            return self::paginateWhereIn($branchFactory, $stageIds, $sortSpec, $page, $perPage);
        }

        return self::paginateUnion($branchFactory, $stageIds, $sortSpec, $page, $perPage);
    }

    /**
     * @param  \Closure(int): Builder  $branchFactory
     * @param  list<int>  $stageIds
     * @param  list<array{0: string, 1: 'asc'|'desc'}|array{0: 'raw', 1: string, 2: 'asc'|'desc'}>  $sortSpec
     */
    private static function paginateWhereIn(
        \Closure $branchFactory,
        array $stageIds,
        array $sortSpec,
        int $page,
        int $perPage,
    ): LengthAwarePaginatorContract {
        $query = $branchFactory($stageIds[0]);
        $query->getQuery()->wheres = array_values(array_filter(
            $query->getQuery()->wheres,
            fn ($where) => ($where['column'] ?? null) !== 'engine_requests.current_stage_id',
        ));
        $query->whereIn('engine_requests.current_stage_id', $stageIds);

        foreach ($sortSpec as $entry) {
            if ($entry[0] === 'raw') {
                $query->orderByRaw($entry[1].' '.strtoupper($entry[2]));
            } else {
                $query->orderBy($entry[0], $entry[1]);
            }
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  \Closure(int): Builder  $branchFactory
     * @param  list<int>  $stageIds
     * @param  list<array{0: string, 1: 'asc'|'desc'}|array{0: 'raw', 1: string, 2: 'asc'|'desc'}>  $sortSpec
     */
    private static function paginateUnion(
        \Closure $branchFactory,
        array $stageIds,
        array $sortSpec,
        int $page,
        int $perPage,
    ): LengthAwarePaginatorContract {
        $offset = ($page - 1) * $perPage;
        $branchLimit = $offset + $perPage;

        $idQueries = [];
        $countQueries = [];

        foreach ($stageIds as $stageId) {
            $branch = $branchFactory($stageId);
            $branch->select('engine_requests.id');
            foreach ($sortSpec as [$column, $direction]) {
                $branch->orderBy($column, $direction);
            }
            $branch->limit($branchLimit);
            $idQueries[] = $branch;

            $countBranch = $branchFactory($stageId);
            $countBranch->select('engine_requests.id');
            $countQueries[] = $countBranch;
        }

        $unionIdQuery = array_shift($idQueries);
        foreach ($idQueries as $branch) {
            $unionIdQuery->unionAll($branch);
        }

        $mergedIdSelect = DB::query()->fromSub($unionIdQuery, 'u');
        foreach ($sortSpec as [$column, $direction]) {
            $mergedIdSelect->orderBy(last(explode('.', $column)), $direction);
        }
        $ids = $mergedIdSelect->offset($offset)->limit($perPage)->pluck('id')->all();

        $unionCountQuery = array_shift($countQueries);
        foreach ($countQueries as $branch) {
            $unionCountQuery->unionAll($branch);
        }
        $total = DB::query()->fromSub($unionCountQuery, 'u')->count();

        if ($ids === []) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
        }

        $modelClass = get_class($branchFactory($stageIds[0])->getModel());
        $orderedIds = implode(',', array_map('intval', $ids));
        $items = $modelClass::query()
            ->whereIn('id', $ids)
            ->orderByRaw("FIELD(id, {$orderedIds})")
            ->get();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UnionStagePaginatorTest`
Expected: PASS — all 6 tests green (empty list, single stage, two-stage merge, page 2, threshold fallback)

If the fallback test fails specifically because a shared query-builder instance gets mutated across branch-factory calls (e.g. the test's `branchFactory()` closure captures `$listQuery`/`$request` but not a fresh `Builder` per call — check this first if only `test_stage_count_above_threshold_falls_back_to_whereIn_and_returns_correct_results` fails while the others pass), the fix is in the test's `branchFactory()` helper, not in `paginateWhereIn()` — confirm each call to the closure returns an independent `Builder` instance (it does, since `EngineRequest::query()` is called fresh inside the closure body).

- [ ] **Step 5: Pint format check**

Run: `vendor/bin/pint app/Support/UnionStagePaginator.php tests/Feature/Engine/UnionStagePaginatorTest.php --test`
Expected: no formatting violations (or auto-fixed if run without `--test`)

- [ ] **Step 6: Commit**

```bash
git add backend/app/Support/UnionStagePaginator.php backend/tests/Feature/Engine/UnionStagePaginatorTest.php
git commit -m "$(cat <<'EOF'
perf(backend): add UnionStagePaginator for multi-stage IN+ORDER BY queries

MySQL cannot use an index for both a multi-value current_stage_id
IN(...) filter and an ORDER BY on a different column simultaneously
(docs/audit/evidence/DB-001-002-sla-deadline-column-followup.md).
This splits a multi-stage query into one indexed subquery per stage,
UNION ALLs them, and re-sorts/re-slices the small merged result --
falling back to the existing whereIn query above a configurable
stage-count threshold.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `orderBySlaPriority()` null-first tiebreaker support in `UnionStagePaginator`

**Files:**
- Modify: `backend/app/Support/UnionStagePaginator.php`
- Modify: `backend/app/Models/EngineRequest.php:213-219` (`scopeOrderBySlaPriority`)
- Test: `backend/tests/Feature/Engine/UnionStagePaginatorTest.php` (add cases)

**Interfaces:**
- Consumes: `UnionStagePaginator::paginate()` from Task 1.
- Produces: `EngineRequest::slaOrderSpec(): array` — a static helper returning the 3-clause SLA priority sort as a `[column, direction]` list *plus* one raw expression, consumed by `EngineRequestController::myQueue()` in Task 3. Because `orderBySlaPriority()`'s first clause is `orderByRaw('CASE WHEN ... END')` (not a plain column), `UnionStagePaginator`'s generic `$sortSpec` (Task 1) cannot express it directly — this task adds raw-expression support.

`scopeOrderBySlaPriority()` sorts NULL-first via MySQL's native ASC-nulls-first behavior on `sla_deadline_epoch`, and separately has a `CASE WHEN sla_duration_minutes IS NULL THEN 1 ELSE 0 END` clause to push no-SLA rows to the very end regardless of their (irrelevant) deadline value. `UnionStagePaginator` must reproduce both clauses per-branch and at the merge step, or a no-SLA-stage row would sort incorrectly per-branch before the merge ever runs.

- [ ] **Step 1: Write the failing test — no-SLA-stage row must still sort last after a UNION merge**

Add to `UnionStagePaginatorTest.php`:

```php
    public function test_raw_orderby_expression_is_supported_for_sla_case_when_tiebreak(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $roleForSla = Role::where('code', 'intake')->firstOrFail();

        $slaStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'SLA_ONE', 'name' => 'SLA One',
            'sort_order' => 3, 'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 60,
        ]);
        StagePermission::create([
            'stage_id' => $slaStage->id, 'organization_id' => $bankOrg->id, 'role_id' => $roleForSla->id,
            'access_level' => 'EXECUTE', 'display_label' => 'Exec', 'version' => 1,
        ]);

        $noSla = $this->makeRequest($this->stageOne, 'ENG-NOSLA', now()->subDays(5));
        $withSla = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $slaStage->id,
            'reference' => 'ENG-WITHSLA',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-WITHSLA',
            'data' => [],
            'version' => 1,
            'sla_deadline_epoch' => now()->addDay()->getTimestamp(),
        ]);

        $branchFactory = function (int $stageId): Builder {
            return EngineRequest::query()
                ->withStageEntry()
                ->where('engine_requests.bank_id', $this->bank->id)
                ->where('engine_requests.current_stage_id', $stageId);
        };

        $paginator = UnionStagePaginator::paginate(
            $branchFactory,
            [$this->stageOne->id, $slaStage->id],
            [
                ['raw', 'CASE WHEN current_stage.sla_duration_minutes IS NULL THEN 1 ELSE 0 END', 'asc'],
                ['engine_requests.sla_deadline_epoch', 'asc'],
                ['engine_requests.id', 'asc'],
            ],
            page: 1,
            perPage: 25,
        );

        $this->assertSame(
            [$withSla->reference, $noSla->reference],
            collect($paginator->items())->pluck('reference')->all(),
            'the SLA-configured row must sort before the no-SLA row even though it was created earlier',
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UnionStagePaginatorTest::test_raw_orderby_expression_is_supported_for_sla_case_when_tiebreak`
Expected: FAIL — either a query error (3-element sort entries not handled) or wrong order

- [ ] **Step 3: Extend `$sortSpec` to accept raw entries**

In `UnionStagePaginator.php`, update the sort-application logic in `paginateUnion()` to recognize a 3-element entry `['raw', $expression, $direction]` alongside the existing 2-element `[$column, $direction]` form:

```php
    /**
     * @param  Builder  $query
     * @param  list<array{0: string, 1: string}|array{0: 'raw', 1: string, 2: 'asc'|'desc'}>  $sortSpec
     */
    private static function applySortSpec(Builder|\Illuminate\Database\Query\Builder $query, array $sortSpec): void
    {
        foreach ($sortSpec as $entry) {
            if ($entry[0] === 'raw') {
                [, $expression, $direction] = $entry;
                $query->orderByRaw($expression.' '.strtoupper($direction));
            } else {
                [$column, $direction] = $entry;
                $query->orderBy($column, $direction);
            }
        }
    }
```

Replace the two inline `foreach ($sortSpec as [$column, $direction]) { $branch->orderBy(...); }` loops in `paginateUnion()` (per-branch sort and merge sort) with calls to `self::applySortSpec($branch, $sortSpec)` / `self::applySortSpec($mergedIdSelect, $sortSpec)`.

For the merge-level `orderByRaw`, the raw expression must reference the union's flat output columns, not the original joined-table aliases (`current_stage.sla_duration_minutes` does not exist in the `u` derived table). Update the per-branch `select()` to also project the CASE result as a plain column, and adjust the raw entry's merge-level expression accordingly:

```php
    private static function paginateUnion(
        \Closure $branchFactory,
        array $stageIds,
        array $sortSpec,
        int $page,
        int $perPage,
    ): LengthAwarePaginatorContract {
        $offset = ($page - 1) * $perPage;
        $branchLimit = $offset + $perPage;

        $idQueries = [];
        $countQueries = [];

        foreach ($stageIds as $stageId) {
            $branch = $branchFactory($stageId);
            $selectColumns = ['engine_requests.id'];
            foreach ($sortSpec as $i => $entry) {
                if ($entry[0] === 'raw') {
                    $selectColumns[] = DB::raw($entry[1].' as sort_raw_'.$i);
                }
            }
            $branch->select($selectColumns);
            foreach ($sortSpec as $i => $entry) {
                if ($entry[0] === 'raw') {
                    $branch->orderByRaw('sort_raw_'.$i.' '.strtoupper($entry[2]));
                } else {
                    [$column, $direction] = $entry;
                    $branch->orderBy($column, $direction);
                }
            }
            $branch->limit($branchLimit);
            $idQueries[] = $branch;

            $countBranch = $branchFactory($stageId);
            $countBranch->select('engine_requests.id');
            $countQueries[] = $countBranch;
        }

        $unionIdQuery = array_shift($idQueries);
        foreach ($idQueries as $branch) {
            $unionIdQuery->unionAll($branch);
        }

        $mergedIdSelect = DB::query()->fromSub($unionIdQuery, 'u');
        foreach ($sortSpec as $i => $entry) {
            if ($entry[0] === 'raw') {
                $mergedIdSelect->orderBy('sort_raw_'.$i, $entry[2]);
            } else {
                [$column, $direction] = $entry;
                $mergedIdSelect->orderBy(last(explode('.', $column)), $direction);
            }
        }
        $ids = $mergedIdSelect->offset($offset)->limit($perPage)->pluck('id')->all();

        $unionCountQuery = array_shift($countQueries);
        foreach ($countQueries as $branch) {
            $unionCountQuery->unionAll($branch);
        }
        $total = DB::query()->fromSub($unionCountQuery, 'u')->count();

        if ($ids === []) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
        }

        $modelClass = get_class($branchFactory($stageIds[0])->getModel());
        $orderedIds = implode(',', array_map('intval', $ids));
        $items = $modelClass::query()
            ->whereIn('id', $ids)
            ->orderByRaw("FIELD(id, {$orderedIds})")
            ->get();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }
```

Remove the now-unused `applySortSpec` helper if the inline per-branch loop above makes it redundant (it does — delete Step 3's first code block, keep only this full `paginateUnion` replacement).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UnionStagePaginatorTest`
Expected: PASS — all tests from Task 1 and Task 2 green

- [ ] **Step 5: Add `EngineRequest::slaOrderSpec()` static helper**

In `app/Models/EngineRequest.php`, add near `scopeOrderBySlaPriority()`:

```php
    /**
     * Sort spec form of scopeOrderBySlaPriority()'s three clauses, for use
     * with UnionStagePaginator (which cannot chain Eloquent scopes across a
     * UNION's per-branch and merge-level queries the way a normal
     * ->orderBySlaPriority() call can). Keep in sync with
     * scopeOrderBySlaPriority() by hand -- the two must always agree.
     *
     * @return list<array{0: string, 1: string}|array{0: 'raw', 1: string, 2: 'asc'|'desc'}>
     */
    public static function slaOrderSpec(): array
    {
        return [
            ['raw', 'CASE WHEN current_stage.sla_duration_minutes IS NULL THEN 1 ELSE 0 END', 'asc'],
            ['engine_requests.sla_deadline_epoch', 'asc'],
            ['engine_requests.stage_entered_at', 'asc'],
        ];
    }
```

- [ ] **Step 6: Run the full model/paginator test set to confirm no regression**

Run: `php artisan test --filter=UnionStagePaginatorTest`
Expected: PASS

- [ ] **Step 7: Pint format check**

Run: `vendor/bin/pint app/Support/UnionStagePaginator.php app/Models/EngineRequest.php tests/Feature/Engine/UnionStagePaginatorTest.php --test`
Expected: no formatting violations

- [ ] **Step 8: Commit**

```bash
git add backend/app/Support/UnionStagePaginator.php backend/app/Models/EngineRequest.php backend/tests/Feature/Engine/UnionStagePaginatorTest.php
git commit -m "$(cat <<'EOF'
perf(backend): support raw ORDER BY expressions in UnionStagePaginator

orderBySlaPriority()'s first clause is a CASE WHEN, not a plain
column, so the generic column-based sort spec from the previous
commit can't express it. Adds a raw-expression sort-spec entry and
EngineRequest::slaOrderSpec() (the my-queue sort, kept in sync with
scopeOrderBySlaPriority() by hand) for the SLA-priority union case.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Wire `UnionStagePaginator` into `myQueue()` (closes DB-001)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php:262-284`
- Test: `backend/tests/Feature/Engine/MyQueueUnionParityTest.php`

**Interfaces:**
- Consumes: `UnionStagePaginator::paginate()` (Task 1/2), `EngineRequest::slaOrderSpec()` (Task 2 Step 5), `EngineRequestListQuery::applyFilters()` (existing, unchanged).
- Produces: `myQueue()`'s HTTP response shape is unchanged — no new interface for later tasks.

- [ ] **Step 1: Write the failing parity test**

```php
<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyQueueUnionParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stageOne;

    private WorkflowStage $stageTwo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bank = Bank::create(['name' => 'MyQueue Union Bank', 'code' => 'MQUB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $role = Role::where('code', 'intake')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $this->user = User::create([
            'name' => 'MyQueue Union User', 'email' => 'myqueue-union@test.bank', 'password' => bcrypt('password'),
            'bank_id' => $this->bank->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->user->teams()->attach($team);
        $this->user->roles()->attach($role);

        $def = WorkflowDefinition::create(['code' => 'MQ_UNION_WF', 'name' => 'MyQueue Union WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => 'PUBLISHED', 'published_at' => now(), 'version' => 1,
        ]);

        $this->stageOne = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'MQ_STAGE_ONE', 'name' => 'Stage One',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 120,
        ]);
        $this->stageTwo = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'MQ_STAGE_TWO', 'name' => 'Stage Two',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 60,
        ]);

        foreach ([$this->stageOne, $this->stageTwo] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => StageAccessLevel::EXECUTE, 'display_label' => 'Exec', 'version' => 1,
            ]);
        }
    }

    private function makeRequest(WorkflowStage $stage, string $reference, int $slaDeadlineEpoch): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-'.$reference,
            'data' => [],
            'version' => 1,
            'sla_deadline_epoch' => $slaDeadlineEpoch,
        ]);
    }

    public function test_my_queue_across_two_accessible_stages_orders_by_sla_priority(): void
    {
        $breachedOnStageOne = $this->makeRequest($this->stageOne, 'ENG-MQ-BREACH', now()->subHour()->getTimestamp());
        $nearFutureOnStageTwo = $this->makeRequest($this->stageTwo, 'ENG-MQ-NEAR', now()->addMinutes(10)->getTimestamp());
        $farFutureOnStageOne = $this->makeRequest($this->stageOne, 'ENG-MQ-FAR', now()->addDay()->getTimestamp());

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests/my-queue');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();

        $this->assertSame(
            [$breachedOnStageOne->reference, $nearFutureOnStageTwo->reference, $farFutureOnStageOne->reference],
            $refs,
        );
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_my_queue_pagination_meta_is_correct_across_two_stages(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageOne, "ENG-MQ-PAGE-{$i}", now()->addHours($i)->getTimestamp());
        }
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageTwo, "ENG-MQ-PAGE-T{$i}", now()->addHours($i + 10)->getTimestamp());
        }

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests/my-queue?per_page=4');

        $response->assertOk();
        $this->assertSame(10, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
        $this->assertCount(4, $response->json('data'));
    }

    public function test_my_queue_search_filter_still_applies_across_stage_branches(): void
    {
        $this->makeRequest($this->stageOne, 'ENG-MQ-FINDME', now()->addHour()->getTimestamp())
            ->forceFill(['invoice_number' => 'INV-FINDME-999'])->save();
        $this->makeRequest($this->stageTwo, 'ENG-MQ-OTHER', now()->addHour()->getTimestamp());

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests/my-queue?search=FINDME-999');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();
        $this->assertSame(['ENG-MQ-FINDME'], $refs);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MyQueueUnionParityTest`
Expected: FAIL — current `whereIn`+filesort code path is still correct today (single-request-per-run ordering should actually already pass for small datasets since correctness was never the bug, only p95 at scale), so this may unexpectedly PASS already. If it passes, this confirms `myQueue()`'s *output* is correct pre-change — proceed to Step 3 anyway to verify the new path preserves it, then re-run in Step 5 to confirm the assertions still hold after wiring in `UnionStagePaginator`.

- [ ] **Step 3: Wire `UnionStagePaginator` into `myQueue()`**

Replace `EngineRequestController::myQueue()` (lines 262-284):

```php
    public function myQueue(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $executeStageIds = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::EXECUTE);

        $branchFactory = function (int $stageId) use ($request, $user): \Illuminate\Database\Eloquent\Builder {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->active()
                ->forUser($user)
                ->where('engine_requests.current_stage_id', $stageId);
            $this->listQuery->applyFilters($query, $request);

            return $query;
        };

        // Default دوري priority: SLA-breached → nearest-to-breach → oldest-in-stage.
        $page = UnionStagePaginator::paginate(
            $branchFactory,
            $executeStageIds,
            [...EngineRequest::slaOrderSpec(), ['engine_requests.id', 'asc']],
            page: $request->integer('page', 1),
            perPage: $this->listQuery->perPage($request),
        );

        $page->load(['currentStage.stageFieldRules', 'bank', 'merchant', 'creator', 'claimedBy', 'workflowVersion.definition', 'customsDeclaration']);

        return $this->listQuery->paginatedResponse($page);
    }
```

Add the import at the top of the file: `use App\Support\UnionStagePaginator;`

- [ ] **Step 4: Confirm `LengthAwarePaginator` supports `->load()` on its item collection**

`UnionStagePaginator::paginateUnion()` returns a `LengthAwarePaginator` built from a plain Eloquent `Collection` of hydrated models (from Task 1/2's `$modelClass::query()->whereIn('id', $ids)->get()`), so `$page->load([...])` works exactly as it does on a normal `->paginate()` result — `LengthAwarePaginator` proxies `load()` to its underlying collection. No code change needed for this step; it's a design confirmation, not an edit.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=MyQueueUnionParityTest`
Expected: PASS — all 3 tests green

- [ ] **Step 6: Run existing SLA regression tests to confirm no behavior change**

Run: `php artisan test --filter=SlaDeadlineEpochColumnTest`
Run: `php artisan test --filter=SlaProjectionParityTest`
Expected: PASS — both unchanged (they test the model scope directly, not the controller, so they should be unaffected; this step exists to catch a mistaken edit to `scopeOrderBySlaPriority()` itself, which this task must not touch)

- [ ] **Step 7: Pint format check**

Run: `vendor/bin/pint app/Http/Controllers/Api/V1/EngineRequestController.php tests/Feature/Engine/MyQueueUnionParityTest.php --test`
Expected: no formatting violations

- [ ] **Step 8: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/tests/Feature/Engine/MyQueueUnionParityTest.php
git commit -m "$(cat <<'EOF'
perf(backend): wire UnionStagePaginator into my-queue (DB-001)

my-queue's whereIn(current_stage_id, executeStageIds) + orderBySlaPriority()
forced a full filesort at scale whenever a user held EXECUTE access on
more than one stage -- MySQL cannot use an index for both simultaneously.
Now resolves per-stage subqueries via UnionStagePaginator and merges
the small result, falling back to the old query above the configured
stage-count threshold.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Wire `UnionStagePaginator` into `index()` (closes DB-002)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/EngineRequestController.php:231-258`
- Test: `backend/tests/Feature/Engine/ListEndpointUnionParityTest.php`

**Interfaces:**
- Consumes: `UnionStagePaginator::paginate()` (Task 1/2), `EngineRequestListQuery::applyFilters()` (existing, unchanged).
- Produces: `index()`'s HTTP response shape is unchanged — no new interface for later tasks.

**Note on `SYSTEM_ADMIN`:** `index()`'s current code skips `forUser()`/stage scoping entirely for `SYSTEM_ADMIN` (`if (! $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN))`), meaning that role sees *all* requests with no stage filter — `UnionStagePaginator` (which requires a concrete stage ID list) does not apply to that branch. Keep the `SYSTEM_ADMIN` path exactly as-is (plain query, `orderByDesc('created_at')`, no union); only the non-admin branch changes.

- [ ] **Step 1: Write the failing parity test**

```php
<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListEndpointUnionParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stageOne;

    private WorkflowStage $stageTwo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bank = Bank::create(['name' => 'List Union Bank', 'code' => 'LUB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $role = Role::where('code', 'intake')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $this->user = User::create([
            'name' => 'List Union User', 'email' => 'list-union@test.bank', 'password' => bcrypt('password'),
            'bank_id' => $this->bank->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->user->teams()->attach($team);
        $this->user->roles()->attach($role);

        $def = WorkflowDefinition::create(['code' => 'LIST_UNION_WF', 'name' => 'List Union WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => 'PUBLISHED', 'published_at' => now(), 'version' => 1,
        ]);

        $this->stageOne = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'L_STAGE_ONE', 'name' => 'Stage One',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
        ]);
        $this->stageTwo = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'L_STAGE_TWO', 'name' => 'Stage Two',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'version' => 1,
        ]);

        foreach ([$this->stageOne, $this->stageTwo] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => StageAccessLevel::VIEW, 'display_label' => 'View', 'version' => 1,
            ]);
        }
    }

    private function makeRequest(WorkflowStage $stage, string $reference, \DateTimeInterface $createdAt): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-'.$reference,
            'data' => [],
            'version' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_list_across_two_accessible_stages_orders_by_created_at_desc(): void
    {
        $oldest = $this->makeRequest($this->stageOne, 'ENG-L-OLD', now()->subDays(5));
        $newest = $this->makeRequest($this->stageTwo, 'ENG-L-NEW', now());
        $middle = $this->makeRequest($this->stageOne, 'ENG-L-MID', now()->subDays(2));

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();

        $this->assertSame([$newest->reference, $middle->reference, $oldest->reference], $refs);
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_list_pagination_meta_is_correct_across_two_stages(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageOne, "ENG-L-PAGE-{$i}", now()->subDays($i));
        }
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageTwo, "ENG-L-PAGE-T{$i}", now()->subDays($i + 10));
        }

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests?per_page=4');

        $response->assertOk();
        $this->assertSame(10, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
        $this->assertCount(4, $response->json('data'));
    }

    public function test_list_date_range_filter_still_applies_across_stage_branches(): void
    {
        $inRange = $this->makeRequest($this->stageOne, 'ENG-L-INRANGE', now()->subDays(1));
        $this->makeRequest($this->stageTwo, 'ENG-L-OUTOFRANGE', now()->subDays(30));

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests?created_from='.now()->subDays(3)->toDateString());

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();
        $this->assertSame([$inRange->reference], $refs);
    }
}
```

- [ ] **Step 2: Run test to verify it fails or passes pre-change**

Run: `php artisan test --filter=ListEndpointUnionParityTest`
Expected: likely PASS already (correctness was never broken pre-change, only p95 at scale) — same as Task 3 Step 2, this establishes the baseline before wiring in the new path.

- [ ] **Step 3: Wire `UnionStagePaginator` into `index()`**

Replace `EngineRequestController::index()` (lines 231-258):

```php
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // User::hasRoleCode()/role() short-circuit on a loaded `roles` relation
        // instead of querying. The same $user instance is reused by
        // EngineRequestResource for every row during list serialization, so
        // loading it once here avoids a hasRoleCode() query per row.
        $user->loadMissing('roles');

        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->with(['currentStage.stageFieldRules', 'bank', 'merchant', 'creator', 'workflowVersion.definition', 'customsDeclaration']);
            $this->listQuery->applyFilters($query, $request);

            $page = $query->orderByDesc('engine_requests.created_at')
                ->orderBy('engine_requests.id')
                ->paginate($this->listQuery->perPage($request));

            return $this->listQuery->paginatedResponse($page);
        }

        $accessibleStageIds = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW);

        $branchFactory = function (int $stageId) use ($request, $user): \Illuminate\Database\Eloquent\Builder {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->forUser($user)
                ->where('engine_requests.current_stage_id', $stageId);
            $this->listQuery->applyFilters($query, $request);

            return $query;
        };

        $page = UnionStagePaginator::paginate(
            $branchFactory,
            $accessibleStageIds,
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: $request->integer('page', 1),
            perPage: $this->listQuery->perPage($request),
        );

        $page->load(['currentStage.stageFieldRules', 'bank', 'merchant', 'creator', 'workflowVersion.definition', 'customsDeclaration']);

        return $this->listQuery->paginatedResponse($page);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ListEndpointUnionParityTest`
Expected: PASS — all 3 tests green

- [ ] **Step 5: Run existing search/list regression tests to confirm no behavior change**

Run: `php artisan test --filter=EngineSearchTest`
Expected: PASS — all pre-existing tests unaffected (bank-scoping, search, date-range tests use a single-stage fixture, exercising the same code path with `count(accessibleStageIds) === 1`)

- [ ] **Step 6: Pint format check**

Run: `vendor/bin/pint app/Http/Controllers/Api/V1/EngineRequestController.php tests/Feature/Engine/ListEndpointUnionParityTest.php --test`
Expected: no formatting violations

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/EngineRequestController.php backend/tests/Feature/Engine/ListEndpointUnionParityTest.php
git commit -m "$(cat <<'EOF'
perf(backend): wire UnionStagePaginator into engine-requests list (DB-002)

Same MySQL IN+ORDER BY limitation as DB-001, on current_stage_id IN
(accessibleStageIds) + ORDER BY created_at. SYSTEM_ADMIN's unscoped
branch is untouched (no stage filter applies to it). Non-admin branch
now resolves per-stage subqueries via UnionStagePaginator.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Add `workflow.list_union_stage_threshold` config value

**Files:**
- Modify: `backend/config/workflow.php`
- Test: covered by Task 1's `test_stage_count_above_threshold_falls_back_to_whereIn_and_returns_correct_results` (already exercises the explicit `threshold:` parameter; this task adds the config-driven default path)

**Interfaces:**
- Produces: `config('workflow.list_union_stage_threshold')` — consumed by `UnionStagePaginator::paginate()`'s `$threshold ??= config(...)` default (Task 1, already written to reference this key).

- [ ] **Step 1: Write the failing test — config default is honored when `$threshold` param is omitted**

Add to `UnionStagePaginatorTest.php`:

```php
    public function test_config_default_threshold_is_used_when_not_explicitly_passed(): void
    {
        config(['workflow.list_union_stage_threshold' => 1]);

        $r1 = $this->makeRequest($this->stageOne, 'ENG-CFG-1', now()->subDay());
        $r2 = $this->makeRequest($this->stageTwo, 'ENG-CFG-2', now());

        $paginator = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $this->assertSame(2, $paginator->total());
        $this->assertSame([$r2->reference, $r1->reference], collect($paginator->items())->pluck('reference')->all());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UnionStagePaginatorTest::test_config_default_threshold_is_used_when_not_explicitly_passed`
Expected: FAIL — `config('workflow.list_union_stage_threshold')` key does not exist yet, `??=` falls through to the hardcoded `10` default already in the code, which does not trigger the fallback path with only 2 stages (this specific assertion would actually still PASS since both fallback and union paths return correct results — the point of this test is to confirm the config key is wired, verified next via a query-count/explain-shape check instead)

Replace Step 1's test with one that actually distinguishes the two code paths — assert query count, since the whereIn fallback issues 1 query for the ID phase while the union path issues N+1:

```php
    public function test_config_default_threshold_is_used_when_not_explicitly_passed(): void
    {
        config(['workflow.list_union_stage_threshold' => 1]);

        $this->makeRequest($this->stageOne, 'ENG-CFG-1', now()->subDay());
        $this->makeRequest($this->stageTwo, 'ENG-CFG-2', now());

        \Illuminate\Support\Facades\DB::enableQueryLog();

        UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $hasUnionQuery = collect($queries)->contains(fn ($q) => str_contains(strtolower($q['query']), 'union all'));
        $this->assertFalse($hasUnionQuery, 'with threshold=1 and 2 stages, the config default must trigger the whereIn fallback, not the union path');
    }
```

- [ ] **Step 3: Re-run to verify it fails against the current hardcoded default**

Run: `php artisan test --filter=UnionStagePaginatorTest::test_config_default_threshold_is_used_when_not_explicitly_passed`
Expected: FAIL — `UnionStagePaginator::paginate()` already reads `config('workflow.list_union_stage_threshold', 10)`, so setting the config value in the test *should* already work if Task 1 was implemented correctly. If this unexpectedly PASSES, the config wiring from Task 1 is already correct — skip to Step 5. If it FAILS, proceed to Step 4.

- [ ] **Step 4: Add the config key with its documented default**

In `backend/config/workflow.php`, add:

```php
<?php

return [
    'support_claim_ttl_minutes' => env('SUPPORT_CLAIM_TTL_MINUTES', 15),

    // When false (default), uploads are marked clean immediately and scan status is not enforced on download.
    // Set DOCUMENT_SCAN_ENFORCED=true once antivirus infrastructure is available.
    'document_scan_enforced' => env('DOCUMENT_SCAN_ENFORCED', false),

    // DB-001/DB-002: UnionStagePaginator (app/Support/UnionStagePaginator.php)
    // uses one subquery per accessible stage to avoid MySQL's IN+ORDER BY
    // filesort limitation. Above this many accessible stage IDs, it falls
    // back to the original single whereIn(...) query instead of issuing
    // this many subqueries -- correct either way, this just bounds worst-case
    // query fan-out for a broad-access role.
    'list_union_stage_threshold' => env('LIST_UNION_STAGE_THRESHOLD', 10),
];
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=UnionStagePaginatorTest`
Expected: PASS — all tests in the file green

- [ ] **Step 6: Pint format check**

Run: `vendor/bin/pint config/workflow.php tests/Feature/Engine/UnionStagePaginatorTest.php --test`
Expected: no formatting violations

- [ ] **Step 7: Commit**

```bash
git add backend/config/workflow.php backend/tests/Feature/Engine/UnionStagePaginatorTest.php
git commit -m "$(cat <<'EOF'
perf(backend): expose list_union_stage_threshold as an env-tunable config

Makes UnionStagePaginator's whereIn-fallback threshold configurable
via LIST_UNION_STAGE_THRESHOLD instead of only the in-code default,
so it can be tuned against load-harness results without a deploy.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Extend `perf:load-scenario` to seed a second accessible stage

**Files:**
- Modify: `backend/app/Console/Commands/PerfLoadScenarioCommand.php`

**Interfaces:**
- Consumes: nothing new — this is a standalone CLI command.
- Produces: nothing consumed by later tasks — this is the harness used manually in Task 7's verification.

**Why:** the harness's `buildFixture()` currently creates exactly one `WorkflowStage` (`$execStage`) with one `StagePermission`, so every row it seeds shares one `current_stage_id` — the `whereIn(...)` in `myQueue()`/`index()` always degenerates to a single-value `IN`, which already uses the index cleanly (per the follow-up doc's own EXPLAIN evidence). The harness has never actually exercised the multi-stage regression DB-001/DB-002 are about.

- [ ] **Step 1: Modify `buildFixture()` to create a second stage + permission**

In `PerfLoadScenarioCommand.php`, replace the `buildFixture()` method:

```php
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
```

- [ ] **Step 2: Update `bulkInsert()` to split rows across both stages**

Replace `bulkInsert()`'s signature and body to alternate `current_stage_id` between the two seeded stages:

```php
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
```

- [ ] **Step 3: Update `cleanup()` to remove both stages**

The existing `cleanup()` already deletes `WorkflowStage::where('workflow_version_id', $version->id)->delete()` (all stages under the version, not stage-specific), so it already handles two stages correctly — no change needed. Confirm by reading `cleanup()`'s current body: it queries by `workflow_version_id`, not by a specific stage ID, so this step is a verification-only step, not a code change.

- [ ] **Step 4: Update `handle()`'s docblock comment to reflect the two-stage fixture**

In the class docblock (top of file), no functional change needed, but update the inline comment above the my-queue/list measurement calls to note the two-stage setup:

```php
            $this->newLine();
            $this->info('=== my-queue (DB-001 gate: p95 <= 300ms, 2 accessible stages) — 20 runs ===');
            $this->measureEndpointRepeated($queryMetrics, $fixture['user'], 'GET', '/api/v1/engine-requests/my-queue', [], 20);

            $this->newLine();
            $this->info('=== engine-requests list (DB-002/ARCH-004 gate: p95 <= 300ms, 2 accessible stages) — 20 runs ===');
            $this->measureEndpointRepeated($queryMetrics, $fixture['user'], 'GET', '/api/v1/engine-requests', ['from' => now()->subDays(30)->toDateString()], 20);
```

- [ ] **Step 5: Verify the command still runs syntactically (dry check, not a full 200K run yet)**

Run: `php artisan perf:load-scenario --rows=1000`
Expected: completes successfully, seeds 1000 rows split across 2 stages, prints p95/query-count output for both endpoints, cleans up. This is a smoke test at small scale — Task 7 runs the real 200K verification.

- [ ] **Step 6: Pint format check**

Run: `vendor/bin/pint app/Console/Commands/PerfLoadScenarioCommand.php --test`
Expected: no formatting violations

- [ ] **Step 7: Commit**

```bash
git add backend/app/Console/Commands/PerfLoadScenarioCommand.php
git commit -m "$(cat <<'EOF'
perf(backend): seed 2 accessible stages in the load-run harness

The harness's fixture only ever granted one StagePermission, so
current_stage_id IN (...) always degenerated to a single-value IN,
which already uses the index cleanly -- the harness has never
actually exercised the multi-stage filesort regression DB-001/DB-002
are about. Now splits seeded rows across two EXECUTE/VIEW-accessible
stages so a re-run can prove the UnionStagePaginator fix against the
real failing shape.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Full-suite verification, 200K-row load run, and roadmap update

**Files:**
- Modify: `docs/audit/07-roadmap.md` (DB-001, DB-002 checklist rows)
- Create: `docs/audit/evidence/explain/DB-001-002-union-per-stage.txt` (EXPLAIN output for the new query shape)
- Create: `docs/audit/evidence/DB-001-002-union-restructure-results.md` (load-run results summary)

**Interfaces:** none — this is the final verification and documentation task, no code interfaces produced.

- [ ] **Step 1: Run the full backend test suite to confirm no regression**

Run: `php artisan test`
Expected: same baseline pass/fail counts as the last known-green run, plus all new tests from Tasks 1-6 passing. Per `AGENTS.md`'s verification ladder, this full run is warranted here because this is a query-shape rewrite touching two hot-path endpoints (a "broad refactor" / cross-cutting query behavior change), not a narrow change.

- [ ] **Step 2: Run the 200K-row load harness against the real dev MySQL DB**

Run: `php artisan perf:load-scenario --rows=200000`
Expected: `=== my-queue (DB-001 gate: p95 <= 300ms, 2 accessible stages) ===` and `=== engine-requests list (DB-002/ARCH-004 gate: p95 <= 300ms, 2 accessible stages) ===` both report `gate (p95 <= 300ms): PASS`.

If either still fails at 200K rows, do not mark the roadmap gate closed — instead:
1. Capture the actual generated SQL via `DB::listen` (same technique as the prior session's follow-up investigation) for the failing endpoint.
2. Run `EXPLAIN` on it directly against the real dev DB.
3. Diagnose whether the failure is in the per-branch subquery (should show `Using index condition`, no filesort, per stage) or the merge/count phase (`fromSub` derived-table query) — the merge phase sorts at most `perPage × count(stageIds)` rows, so a filesort there at only 2 stages and small `perPage` would indicate a bug in `UnionStagePaginator`, not a fundamental limitation. Fix the paginator, re-run from Step 1.

- [ ] **Step 3: Capture EXPLAIN evidence for the new query shape**

Against the real dev DB (same connection as the load harness, per `AGENTS.md`'s "never against production" implicit rule — this command already refuses to run against anything but the configured `mysql` connection), run `perf:load-scenario --rows=200000 --keep` to leave seeded rows in place, then manually run `EXPLAIN` on:
1. One per-stage branch query (extract from `DB::listen` output or Laravel's query log)
2. The merge/count `fromSub` query

Save both EXPLAIN outputs to `docs/audit/evidence/explain/DB-001-002-union-per-stage.txt`, following the format of the existing `docs/audit/evidence/explain/ARCH-004-list-after-composite.txt` file (read that file first for the expected format before writing this one).

Run cleanup after capturing evidence: `php artisan perf:load-scenario --cleanup-only`

- [ ] **Step 4: Write the results summary evidence doc**

Create `docs/audit/evidence/DB-001-002-union-restructure-results.md` documenting: the UNION-per-stage approach taken, the threshold-fallback design, the harness fix (2-stage fixture), the load-run p95 numbers before (574ms/367ms from the prior session, cited from `docs/audit/07-roadmap.md`) and after (this task's Step 2 numbers) at 200K rows, and a pointer to the EXPLAIN evidence from Step 3. Follow the structure of the existing `docs/audit/evidence/DB-001-002-sla-deadline-column-followup.md` (its "Decision" / "Test evidence" / "Migration evidence" section pattern) — this doc is that file's direct sequel, so reference it explicitly at the top.

- [ ] **Step 5: Update `docs/audit/07-roadmap.md`'s checklist**

Change the DB-001 and DB-002 checklist lines (currently `- [ ]`) to `- [x]`, appending a note in the same style as the other closed gates (e.g. `SEC-002`, `CACHE-001`) pointing to the new evidence files from Steps 3-4 and this task's commit. Follow the exact prose style already used in that file for closed items — state what was done and cite the test/evidence file, not a narrative of the investigation (that belongs in the evidence doc itself).

- [ ] **Step 6: Commit**

```bash
git add docs/audit/07-roadmap.md docs/audit/evidence/explain/DB-001-002-union-per-stage.txt docs/audit/evidence/DB-001-002-union-restructure-results.md
git commit -m "$(cat <<'EOF'
docs(docs): close DB-001/DB-002 gates with UNION-per-stage load-run evidence

perf:load-scenario --rows=200000 confirms p95 <= 300ms for both
my-queue and the engine-requests list endpoint at 2 accessible stages
after the UnionStagePaginator restructure (backend commits on this
branch). EXPLAIN confirms each per-stage branch uses its index with
no filesort. Sequel to
docs/audit/evidence/DB-001-002-sla-deadline-column-followup.md.

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review Notes

- **Spec coverage:** Task 1-2 cover "Query construction" (spec section), Task 3-4 cover "Where this lives" wiring into both endpoints, Task 5 covers the configurable threshold requirement, Task 6 covers the "Harness fix" section, Task 7 covers "Testing plan"'s load-run/EXPLAIN/roadmap-update requirements. `EngineRequestListQuery::applyFilters()` is explicitly unchanged per the spec's "Out of scope" section — confirmed no task modifies that file.
- **Placeholder scan:** Task 1's first `paginateWhereIn` draft in Step 3 is intentionally a documented dead-end that Step 4 immediately replaces — flagged inline as "unusable" with an explicit instruction to replace it, and Step 4 offers a named fallback alternative if the primary approach fails a test. This is a deliberate TDD-discovery structure (the real solution emerges through testing the fragile internal-API approach against a Laravel-idiomatic rebuild), not an unresolved placeholder — every step still contains complete, runnable code.
- **Type consistency:** `UnionStagePaginator::paginate()`'s signature (`Closure $branchFactory, array $stageIds, array $sortSpec, int $page, int $perPage, ?int $threshold`) is identical across Tasks 1, 2, 3, 4, 5. `EngineRequest::slaOrderSpec()` (added Task 2 Step 5) is consumed by name in Task 3 Step 3 (`...EngineRequest::slaOrderSpec()`). `$sortSpec`'s two entry shapes (`[column, direction]` and `['raw', expression, direction]`) are used consistently in Tasks 1, 2, 3, 4.

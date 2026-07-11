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
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Support\EngineRequestListQuery;
use App\Support\UnionStagePaginator;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-'.$reference,
            'data' => [],
            'version' => 1,
        ]);

        // created_at/updated_at are not in EngineRequest::$fillable, so
        // create() silently discards them and Eloquent's own timestamp
        // behavior stamps both to "now" instead. Force-set and persist the
        // fixture's intended created_at directly so tests can exercise
        // distinct sort order across rows.
        $request->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $request;
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

    /**
     * Final whole-branch review finding: paginateUnion() computed
     * `offset = (page - 1) * perPage` with no floor, unlike Laravel's own
     * Paginator::resolveCurrentPage() (which clamps to >= 1). A
     * caller-supplied page <= 0 (e.g. ?page=0 from a client, which
     * EngineRequestController passes straight through via
     * $request->integer('page', 1)) produced a negative OFFSET -- MySQL
     * rejects this as a syntax error (a live 500); SQLite silently
     * tolerates it, so this was invisible to the whole SQLite-backed
     * suite. paginate() now clamps $page to >= 1 before dispatching to
     * either paginateUnion() or paginateWhereIn().
     */
    public function test_page_zero_or_negative_is_clamped_to_page_one(): void
    {
        $r1 = $this->makeRequest($this->stageOne, 'ENG-CLAMP-001', now()->subDays(2));
        $r2 = $this->makeRequest($this->stageOne, 'ENG-CLAMP-002', now()->subDays(1));

        $zeroPage = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 0,
            perPage: 25,
        );
        $negativePage = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: -3,
            perPage: 25,
        );

        $expected = [$r2->reference, $r1->reference];
        $this->assertSame($expected, collect($zeroPage->items())->pluck('reference')->all());
        $this->assertSame(1, $zeroPage->currentPage());
        $this->assertSame($expected, collect($negativePage->items())->pluck('reference')->all());
        $this->assertSame(1, $negativePage->currentPage());
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

    /**
     * DB-001/DB-002 follow-up (Task 6): paginateUnion() unconditionally
     * selects engine_requests.id, then re-added it via addSelect(... as id)
     * for any sort-spec entry that was itself engine_requests.id (the
     * standard tiebreaker both myQueue() and index() use). SQLite silently
     * tolerates a duplicate column alias in a SELECT list; MySQL rejects it
     * outright ("Duplicate column name 'id'") -- a live perf:load-scenario
     * run against real MySQL caught this as a 500 that no SQLite-backed
     * test (including the two tests immediately above/below, which both
     * already use an id tiebreaker) could ever surface, since they only
     * assert on query *results*, never on the generated SQL shape itself.
     * This test calls the real UnionStagePaginator::paginate() entrypoint
     * (not a reimplementation of its private branch-building logic) and
     * captures the actual SQL Laravel generates via DB::listen(), so it
     * tracks the genuine code path and fails on any database driver if the
     * duplicate-column regression is reintroduced, not just MySQL.
     */
    public function test_union_branch_select_never_duplicates_the_id_column(): void
    {
        $this->makeRequest($this->stageOne, 'ENG-DEDUPE-1', now()->subDay());
        $this->makeRequest($this->stageTwo, 'ENG-DEDUPE-2', now());

        $capturedQueries = [];
        DB::listen(function ($query) use (&$capturedQueries) {
            $capturedQueries[] = $query->sql;
        });

        UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $this->assertNotEmpty($capturedQueries, 'expected at least one query to be issued for a 2-stage union call');

        // The union query nests each branch's SELECT inside subqueries
        // (select "id" from (select * from (select ...) union all select *
        // from (select ...)) as "u" ...), so the duplicate-column risk lives
        // in an INNER select clause, not the outermost one -- match every
        // "select ... from" occurrence in the SQL, not just the first.
        foreach ($capturedQueries as $sql) {
            preg_match_all('/select\s+(.*?)\s+from\s/is', $sql, $allMatches);

            foreach ($allMatches[1] as $selectClause) {
                $aliases = array_map(function ($piece) {
                    $piece = trim($piece);
                    if (preg_match('/\bas\s+"?(\w+)"?$/i', $piece, $m)) {
                        return strtolower($m[1]);
                    }
                    if (preg_match('/"?(\w+)"?\."?(\w+)"?$/i', $piece, $m)) {
                        return strtolower($m[2]);
                    }

                    return strtolower(trim($piece, '"` '));
                }, explode(',', $selectClause));

                $duplicates = array_diff_assoc($aliases, array_unique($aliases));

                $this->assertSame(
                    [],
                    array_values($duplicates),
                    'generated SELECT list must never alias two columns to the same name -- MySQL rejects this '
                    ."as \"Duplicate column name\", even though SQLite silently tolerates it.\nSELECT clause: "
                    ."{$selectClause}\nFull SQL: {$sql}\nColumns: ".implode(', ', $aliases),
                );
            }
        }
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

    public function test_stage_count_above_threshold_falls_back_to_where_in_and_returns_correct_results(): void
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

    /**
     * Final whole-branch review finding: the branch-factory contract
     * applies exactly one current_stage_id where as its own stage-scoping
     * clause -- but a caller (e.g. EngineRequestController::myQueue()/
     * index() via EngineRequestListQuery::applyFilters()'s `?stage_id=`
     * filter) can add a SECOND current_stage_id where on top of that. The
     * old fix removed EVERY current_stage_id where before adding
     * whereIn(stageIds), silently dropping the caller's own stage_id
     * filter and broadening the result set beyond what was requested --
     * invisible to every existing fallback test, none of which combine
     * the above-threshold fallback with a stage_id filter. Fixed by
     * removing only the FIRST current_stage_id where (the branch
     * factory's own), matching the documented ordering (branch factory's
     * where always runs before applyFilters()'s).
     */
    public function test_where_in_fallback_preserves_a_caller_supplied_stage_id_filter(): void
    {
        $matching = $this->makeRequest($this->stageOne, 'ENG-FILTER-MATCH', now()->subDay());
        $this->makeRequest($this->stageTwo, 'ENG-FILTER-OTHER', now());

        $branchFactory = function (int $stageId) {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->where('engine_requests.bank_id', $this->bank->id)
                ->where('engine_requests.current_stage_id', $stageId);
            // Mirrors EngineRequestListQuery::applyFilters()'s ?stage_id=
            // handling: a second where on the same column, applied after
            // the branch factory's own -- narrows the result to stage one
            // only, even though both stageOne and stageTwo are passed as
            // accessible stage IDs below.
            $query->where('engine_requests.current_stage_id', $this->stageOne->id);

            return $query;
        };

        $paginator = UnionStagePaginator::paginate(
            $branchFactory,
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
            threshold: 1,
        );

        $this->assertSame(
            1,
            $paginator->total(),
            'the caller-supplied stage_id=stageOne filter must still narrow results to stage one only, '.
            'not be silently dropped alongside the branch factory\'s own current_stage_id where',
        );
        $this->assertSame([$matching->reference], collect($paginator->items())->pluck('reference')->all());
    }

    public function test_where_in_fallback_is_correct_when_a_non_stage_where_value_collides_with_a_stage_id(): void
    {
        // Regression test for the Critical binding-desync bug: the old fix
        // located the current_stage_id binding to remove via
        // array_search($value, $bindings, true), which finds the *first*
        // binding with a matching value rather than the positionally
        // correct one.
        //
        // paginateWhereIn() builds its single fallback query from ONLY
        // stageIds[0]'s branch (see the $branchFactory($stageIds[0]) call),
        // then widens that one branch's current_stage_id filter into a
        // whereIn covering every stage ID. So the collision that matters is
        // between a *different* where clause on stage one's branch (e.g.
        // workflow_version_id, mirroring EngineRequestListQuery::
        // applyFilters()'s pattern) and *stage two's* ID -- if
        // array_search(stageId_to_remove, $bindings) matches the
        // workflow_version_id binding first (because its value happens to
        // equal stage two's id) instead of the current_stage_id binding,
        // the wrong binding is stripped and the workflow_version_id filter
        // silently vanishes, corrupting the query.
        //
        // Build a second WorkflowVersion whose id is deliberately made to
        // equal $this->stageTwo->id to force that exact collision.
        $collidingVersion = null;
        for ($i = 0; $i < 50; $i++) {
            $candidate = WorkflowVersion::create([
                'workflow_definition_id' => $this->version->workflow_definition_id,
                'version_number' => 100 + $i,
                'state' => 'PUBLISHED',
                'published_at' => now(),
                'version' => 1,
            ]);

            if ($candidate->id === $this->stageTwo->id) {
                $collidingVersion = $candidate;
                break;
            }

            $candidate->delete();
        }

        $this->assertNotNull(
            $collidingVersion,
            'Could not force a WorkflowVersion id collision with stageTwo->id within 50 attempts; '.
            'bump the attempt count or align the id ranges.',
        );

        // r1: stage one, scoped to the colliding workflow_version_id (the value that
        // numerically equals stageTwo->id -- the collision the old fix mishandled).
        $r1 = EngineRequest::create([
            'workflow_version_id' => $collidingVersion->id,
            'current_stage_id' => $this->stageOne->id,
            'reference' => 'ENG-COLLIDE-1',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-ENG-COLLIDE-1',
            'data' => [],
            'version' => 1,
        ]);
        $r1->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        // r2: stage two, same colliding workflow_version_id, so it also passes the
        // workflow_version_id filter -- must be returned once the current_stage_id
        // filter is correctly widened to whereIn(stageOne, stageTwo).
        $r2 = EngineRequest::create([
            'workflow_version_id' => $collidingVersion->id,
            'current_stage_id' => $this->stageTwo->id,
            'reference' => 'ENG-COLLIDE-2',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-ENG-COLLIDE-2',
            'data' => [],
            'version' => 1,
        ]);
        $r2->forceFill(['created_at' => now(), 'updated_at' => now()])->save();

        // A decoy row on a *different* workflow_version_id must never appear: if the
        // old bug strips the workflow_version_id binding instead of current_stage_id,
        // this row would incorrectly leak into the results.
        $decoy = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stageOne->id,
            'reference' => 'ENG-COLLIDE-DECOY',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-ENG-COLLIDE-DECOY',
            'data' => [],
            'version' => 1,
        ]);
        $decoy->forceFill(['created_at' => now()->subHours(12), 'updated_at' => now()->subHours(12)])->save();

        $branchFactory = function (int $stageId) use ($collidingVersion): Builder {
            // Three where clauses on the branch query, mirroring
            // EngineRequestListQuery::applyFilters()'s bank_id +
            // workflow_version_id + current_stage_id pattern -- the shape that
            // hides the Critical bug when only two clauses are present.
            return EngineRequest::query()
                ->withStageEntry()
                ->where('engine_requests.bank_id', $this->bank->id)
                ->where('engine_requests.workflow_version_id', $collidingVersion->id)
                ->where('engine_requests.current_stage_id', $stageId);
        };

        $paginator = UnionStagePaginator::paginate(
            $branchFactory,
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
            threshold: 1,
        );

        $this->assertSame(2, $paginator->total());
        $this->assertSame(
            [$r2->reference, $r1->reference],
            collect($paginator->items())->pluck('reference')->all(),
            'Only the two colliding-version rows must come back, correctly ordered, and '.
            'the decoy on a different workflow_version_id must be excluded -- proves the '.
            'fix strips the positionally correct current_stage_id binding rather than the '.
            'first value-matching binding (which would be workflow_version_id here).',
        );
    }

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

    public function test_union_path_hydration_preserves_stage_entered_at_coalesce_fallback(): void
    {
        // Regression test for the Important hydration bug: paginateUnion()'s final
        // hydration step used a plain `$modelClass::query()->whereIn('id', $ids)->get()`
        // with no withStageEntry() scope, so any row relying on the
        // stage_entered_at COALESCE fallback (pre-backfill / legacy rows whose
        // maintained column is still null) came back with stage_entered_at = null
        // and therefore sla_status = null, instead of the real ok/nearing/breached
        // value the old whereIn-only code path (and this class's own
        // paginateWhereIn() fallback) would have derived.
        //
        // stageOne carries no sla_duration_minutes (see setUp()), so use a second
        // stage configured with one, mirroring
        // test_raw_orderby_expression_is_supported_for_sla_case_when_tiebreak()'s
        // pattern of adding a dedicated SLA stage + StagePermission grant.
        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $roleForSla = Role::where('code', 'intake')->firstOrFail();

        $slaStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'SLA_FALLBACK', 'name' => 'SLA Fallback',
            'sort_order' => 4, 'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 60,
        ]);
        StagePermission::create([
            'stage_id' => $slaStage->id, 'organization_id' => $bankOrg->id, 'role_id' => $roleForSla->id,
            'access_level' => 'EXECUTE', 'display_label' => 'Exec', 'version' => 1,
        ]);

        // Legacy row: stage_entered_at left null on the maintained column (simulating
        // a pre-backfill row), so scopeWithStageEntry()'s COALESCE fallback must read
        // workflow_history's max(created_at) for this request/stage instead.
        $legacyRequest = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $slaStage->id,
            'reference' => 'ENG-LEGACY-FALLBACK',
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-ENG-LEGACY-FALLBACK',
            'data' => [],
            'version' => 1,
        ]);
        $enteredAt = now()->subMinutes(10);
        $legacyRequest->forceFill(['created_at' => $enteredAt, 'updated_at' => $enteredAt, 'stage_entered_at' => null])->save();

        // Mirrors SlaProjectionParityTest::requestOnStage()'s fixture pattern: the
        // COALESCE fallback reads max(created_at) from workflow_history where
        // to_stage_id matches the request's current stage.
        WorkflowHistoryEntry::create([
            'request_id' => $legacyRequest->id,
            'to_stage_id' => $slaStage->id,
            'from_stage_id' => $this->stageOne->id,
            'performed_by' => $this->user->id,
            'action_code' => 'ENTER',
            'created_at' => $enteredAt,
        ]);

        // A second, unrelated row on a different accessible stage forces the union
        // path (2+ stages), not the paginateWhereIn() fallback.
        $this->makeRequest($this->stageOne, 'ENG-OTHER', now());

        $paginator = UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $slaStage->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $hydrated = collect($paginator->items())->firstWhere('reference', 'ENG-LEGACY-FALLBACK');

        $this->assertNotNull($hydrated, 'the legacy fallback row must be present in the union-path results');
        $this->assertNotNull(
            $hydrated->stage_entered_at,
            'union-path hydration must apply withStageEntry() so the COALESCE fallback resolves stage_entered_at from workflow_history, not leave it null',
        );
        $this->assertNotNull(
            $hydrated->sla_status,
            'sla_status must be derivable via the COALESCE fallback on the union hydration path, matching the direct-query and paginateWhereIn() behaviour',
        );
    }

    public function test_config_default_threshold_is_used_when_not_explicitly_passed(): void
    {
        config(['workflow.list_union_stage_threshold' => 1]);

        $this->makeRequest($this->stageOne, 'ENG-CFG-1', now()->subDay());
        $this->makeRequest($this->stageTwo, 'ENG-CFG-2', now());

        DB::enableQueryLog();

        UnionStagePaginator::paginate(
            $this->branchFactory(),
            [$this->stageOne->id, $this->stageTwo->id],
            [['engine_requests.created_at', 'desc'], ['engine_requests.id', 'asc']],
            page: 1,
            perPage: 25,
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $hasUnionQuery = collect($queries)->contains(fn ($q) => str_contains(strtolower($q['query']), 'union all'));
        $this->assertFalse($hasUnionQuery, 'with threshold=1 and 2 stages, the config default must trigger the whereIn fallback, not the union path');
    }
}

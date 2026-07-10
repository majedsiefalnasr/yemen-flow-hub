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
}

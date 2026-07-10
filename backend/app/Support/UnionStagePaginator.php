<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
     * @param  list<array{0: string, 1: 'asc'|'desc'}|array{0: 'raw', 1: string, 2: 'asc'|'desc'}|array{0: 'raw', 1: string, 2: 'asc'|'desc', 3: bool}>  $sortSpec  Column names must be fully table-qualified (e.g. 'engine_requests.created_at'), not bare (e.g. 'created_at'). paginateUnion() aliases each sort column to its bare (unqualified) name for the outer merge-and-resort query; an unqualified input column risks colliding with a same-named column pulled in via EngineRequest::scopeWithStageEntry()'s `current_stage` join (e.g. workflow_stages.id, workflow_stages.created_at). A `raw` entry's optional 4th element ($stageInvariant, default false) marks the expression as provably constant for every row within one accessible stage (e.g. it reads only stage-level joined columns like current_stage.sla_duration_minutes, never a per-row engine_requests column) -- see paginateUnion()'s docblock for why this unlocks a real optimization and why it must stay opt-in, not inferred.
     * @param  string|null  $forceIndex  MySQL index name to force on each per-branch query's engine_requests table (see paginateUnion()'s per-branch loop). Needed because MySQL's optimizer makes a DIFFERENT (and here, worse) index choice for the identical query executed as a UNION ALL branch/derived table than it makes for the same query run standalone -- confirmed via EXPLAIN ANALYZE: a per-branch query alone used the sort-covering composite index (er_stage_sla_deadline / er_stage_created) in ~3ms, but the same query nested inside paginateUnion()'s UNION ALL picked an unrelated bank_id index and scanned the full stage's row set (~150-180ms per branch) instead. This is a documented MySQL cost-estimation quirk specific to derived-table contexts, not a missing index -- forcing the index closes it. Ignored on non-MySQL drivers (see paginateUnion()).
     */
    public static function paginate(
        \Closure $branchFactory,
        array $stageIds,
        array $sortSpec,
        int $page,
        int $perPage,
        ?int $threshold = null,
        ?string $forceIndex = null,
    ): LengthAwarePaginatorContract {
        if ($stageIds === []) {
            // An empty Eloquent Collection, not a bare array or a base
            // Illuminate\Support\Collection: callers such as
            // EngineRequestController::myQueue() call $page->load([...]) on
            // the returned paginator. LengthAwarePaginator wraps a bare array
            // in Support\Collection, and only Eloquent\Collection defines
            // load() -- both other shapes throw "Method ... ::load does not
            // exist." Eloquent\Collection::load() itself is a safe no-op when
            // empty.
            return new LengthAwarePaginator(new EloquentCollection, 0, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
        }

        $threshold ??= config('workflow.list_union_stage_threshold', 10);

        if (count($stageIds) > $threshold) {
            return self::paginateWhereIn($branchFactory, $stageIds, $sortSpec, $page, $perPage);
        }

        return self::paginateUnion($branchFactory, $stageIds, $sortSpec, $page, $perPage, $forceIndex);
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
        $baseQuery = $query->getQuery();

        // Filtering `wheres` alone leaves stale entries in the parallel
        // `bindings['where']` array (Laravel keeps them as two independent,
        // positionally-appended arrays), which desyncs placeholders from
        // values for every remaining bound where clause. We cannot correlate
        // the entry to remove by searching bindings *by value*: another
        // where clause in the same branch (bank_id, workflow_version_id,
        // merchant_id, etc. — see EngineRequestListQuery::applyFilters())
        // can bind a value that is numerically equal to the stage ID being
        // removed, so array_search(..., $bindings, true) would find that
        // other binding first and strip it instead, silently corrupting the
        // query. Instead we track the *positional* binding slot the
        // current_stage_id where entry occupies by walking `wheres` in
        // order and counting how many binding slots each where type
        // consumes before it (per Laravel's own Builder::where()/whereIn()/
        // whereNull() slot-consumption rules), then splice that exact slot
        // out of bindings['where'].
        $bindingSlotsToRemove = [];
        $bindingIndex = 0;
        $baseQuery->wheres = array_values(array_filter(
            $baseQuery->wheres,
            function ($where) use (&$bindingIndex, &$bindingSlotsToRemove) {
                $slots = self::bindingSlotCount($where);

                if (($where['column'] ?? null) === 'engine_requests.current_stage_id') {
                    for ($i = 0; $i < $slots; $i++) {
                        $bindingSlotsToRemove[] = $bindingIndex + $i;
                    }
                    $bindingIndex += $slots;

                    return false;
                }

                $bindingIndex += $slots;

                return true;
            },
        ));

        if ($bindingSlotsToRemove !== []) {
            $bindings = $baseQuery->bindings['where'] ?? [];
            foreach ($bindingSlotsToRemove as $slot) {
                unset($bindings[$slot]);
            }
            $baseQuery->bindings['where'] = array_values($bindings);
        }

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
     * Number of `bindings['where']` slots a single Laravel query-builder
     * `wheres` entry consumes, mirroring Illuminate\Database\Query\Builder's
     * own binding-producing methods exactly (verified against the installed
     * framework version's where()/whereIn()/whereNull()/whereBetween()/
     * whereRaw()/whereNested()/whereSub()/addWhereExistsQuery()):
     *
     * - `Basic` / `Bitwise` / `JsonBoolean` (plain where()): 1 slot, unless
     *   the value is a raw Expression, which binds nothing.
     * - `In` / `NotIn`: one slot per value.
     * - `Null` / `NotNull` / `betweenColumns`: 0 slots — no value is bound.
     * - `between`: binds up to 2 slots.
     * - `raw` (whereRaw()): however many bindings were explicitly passed.
     * - `Nested` (where(Closure)), `Sub`, `Exists` / `NotExists`
     *   (whereHas()/whereExists()): these embed a full nested `query`
     *   builder in the where entry itself and Laravel binds that embedded
     *   query's *own* where bindings verbatim — so the slot count is simply
     *   that embedded query's binding count, not a hardcoded guess.
     * - anything else (column comparisons, row values, expressions, etc.):
     *   0, since none of those are produced by this class's branch-factory
     *   contract (EngineRequestListQuery::applyFilters()'s where/whereIn/
     *   whereNull/whereRaw/nested-where/whereHas filters).
     */
    private static function bindingSlotCount(array $where): int
    {
        return match ($where['type'] ?? null) {
            'Basic', 'Bitwise', 'JsonBoolean' => ($where['value'] ?? null) instanceof Expression ? 0 : 1,
            'In', 'NotIn' => is_countable($where['values'] ?? null) ? count($where['values']) : 0,
            'Null', 'NotNull', 'betweenColumns' => 0,
            'between' => 2,
            'raw' => is_array($where['bindings'] ?? null) ? count($where['bindings']) : 0,
            'Nested' => isset($where['query']) ? count($where['query']->getRawBindings()['where'] ?? []) : 0,
            'Sub', 'Exists', 'NotExists' => isset($where['query']) ? count($where['query']->getBindings()) : 0,
            default => 0,
        };
    }

    /**
     * @param  \Closure(int): Builder  $branchFactory
     * @param  list<int>  $stageIds
     * @param  list<array{0: string, 1: 'asc'|'desc'}|array{0: 'raw', 1: string, 2: 'asc'|'desc'}|array{0: 'raw', 1: string, 2: 'asc'|'desc', 3: bool}>  $sortSpec  Column names must be fully table-qualified (e.g. 'engine_requests.created_at'), not bare (e.g. 'created_at'), to avoid ambiguity against EngineRequest::scopeWithStageEntry()'s `current_stage` join (workflow_stages aliased as current_stage, which has its own id/created_at columns). Each column is aliased below to its bare name for the outer merge-and-resort query, so an unqualified caller column could silently collide with a current_stage column of the same name. A `['raw', ...]` entry is instead projected under a stable `sort_raw_{index}` alias, since a raw SQL expression has no natural bare column name to reuse.
     *
     * A raw entry's optional 4th element ($stageInvariant, default false)
     * is excluded from the PER-BRANCH ORDER BY (but still SELECTed under
     * its sort_raw_{i} alias, and still included in the outer merge's
     * ORDER BY below) -- a real, measured optimization for exactly one
     * documented case: EngineRequest::slaOrderSpec()'s leading
     * `CASE WHEN current_stage.sla_duration_minutes IS NULL THEN 1 ELSE 0
     * END` clause. Confirmed via EXPLAIN ANALYZE on the real 200K-row load
     * harness: with the CASE in the per-branch ORDER BY, MySQL cannot use
     * the covering sla_deadline_epoch index for the sort (only for the
     * current_stage_id filter), forcing a full per-branch scan+sort
     * (~150ms); with it excluded, the same query drops to under 1ms
     * (index-ordered scan, LIMIT pushed all the way down).
     *
     * This is only safe to skip per-branch because sla_duration_minutes is
     * a STAGE-level column (joined via current_stage), not a row-level
     * engine_requests column -- every row within a single stageId's branch
     * necessarily shares the exact same value, so the CASE evaluates to
     * the same constant for the whole branch and can never discriminate
     * which rows are that branch's "top N" by any other clause. It still
     * matters at the OUTER merge step, where rows from stages that DO vs
     * DON'T have an SLA get interleaved and must be correctly ordered
     * relative to each other -- hence it stays in the merge ORDER BY
     * unconditionally, only the per-branch ORDER BY skips it.
     * $stageInvariant defaults to false and must be explicitly opted into
     * per raw entry -- this optimization is NOT safely inferable in
     * general (a hypothetical future raw expression reading a per-row
     * engine_requests column, e.g. a JSON field, would NOT share this
     * property and skipping it per-branch would silently return the wrong
     * top-N rows).
     * @param  string|null  $forceIndex  See paginate()'s docblock -- MySQL picks a worse index for a per-branch query executed inside a UNION ALL than it does for the same query standalone, so this forces the caller-supplied covering index on both the id-resolution and count branches. Applied via Laravel's forceIndex() on each branch's engine_requests table; ignored entirely on non-MySQL drivers, since forceIndex()'s FORCE INDEX syntax is MySQL-specific and this repo's test suite runs on SQLite.
     */
    private static function paginateUnion(
        \Closure $branchFactory,
        array $stageIds,
        array $sortSpec,
        int $page,
        int $perPage,
        ?string $forceIndex = null,
    ): LengthAwarePaginatorContract {
        $offset = ($page - 1) * $perPage;
        $branchLimit = $offset + $perPage;
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        $idQueries = [];
        $countQueries = [];

        foreach ($stageIds as $stageId) {
            $branch = $branchFactory($stageId);
            if ($forceIndex !== null && $isMysql) {
                $branch->getQuery()->forceIndex($forceIndex);
            }
            // The outer merge query re-sorts by these same columns (by their
            // unqualified alias) once the per-branch results are UNION ALL'd
            // together, so each sort column must be projected here, not just
            // id, or the outer ORDER BY has nothing to sort on.
            $branch->select('engine_requests.id');
            foreach ($sortSpec as $i => $entry) {
                if ($entry[0] === 'raw') {
                    [, $expression, $direction] = $entry;
                    $stageInvariant = $entry[3] ?? false;
                    $branch->addSelect(DB::raw($expression.' as sort_raw_'.$i));
                    if (! $stageInvariant) {
                        $branch->orderByRaw('sort_raw_'.$i.' '.strtoupper($direction));
                    }
                } else {
                    [$column, $direction] = $entry;
                    // A sort-spec column of engine_requests.id (e.g. the usual
                    // final tiebreaker) is already selected above as `id` --
                    // re-adding it via addSelect(... as id) produces a
                    // duplicate `id` column in the SELECT list. MySQL rejects
                    // this outright ("Duplicate column name 'id'"); SQLite
                    // silently tolerates it, which is why this only surfaced
                    // via a real MySQL run (perf:load-scenario), not the
                    // SQLite-backed test suite. orderBy() below still applies
                    // regardless of whether the select was skipped.
                    if ($column !== 'engine_requests.id') {
                        $branch->addSelect($column.' as '.last(explode('.', $column)));
                    }
                    $branch->orderBy($column, $direction);
                }
            }
            $branch->limit($branchLimit);
            $idQueries[] = $branch;

            $countBranch = $branchFactory($stageId);
            if ($forceIndex !== null && $isMysql) {
                $countBranch->getQuery()->forceIndex($forceIndex);
            }
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
            // See the $stageIds === [] branch above for why this must be an
            // empty Eloquent Collection, not a bare array.
            return new LengthAwarePaginator(new EloquentCollection, $total, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
        }

        $modelClass = get_class($branchFactory($stageIds[0])->getModel());

        // DB-001/DB-002 FIELD() portability fix: the brief's original Step 3
        // used `ORDER BY FIELD(id, ...)` to restore the merged SQL order
        // during hydration, but FIELD() is MySQL-only and this repo's test
        // suite runs against SQLite. $ids is already in the correct final
        // order (sorted+limited entirely in SQL above), so instead of an
        // ORDER BY FIELD() expression we re-order the already-fetched,
        // page-sized Eloquent collection in PHP via an array_flip lookup.
        // This is cheap (at most $perPage rows) and portable across both
        // database drivers.
        // withStageEntry() left-joins workflow_stages (aliased current_stage), which
        // also has an `id` column, so the whereIn column must be table-qualified to
        // avoid an ambiguous-column error on MySQL.
        $items = $modelClass::query()->withStageEntry()->whereIn('engine_requests.id', $ids)->get();
        $idOrder = array_flip($ids);
        $items = $items->sortBy(fn ($item) => $idOrder[$item->id])->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }
}

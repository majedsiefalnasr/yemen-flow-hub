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
        $baseQuery = $query->getQuery();

        // Filtering `wheres` alone leaves a stale entry in the parallel
        // `bindings['where']` array (Laravel keeps them as two independent,
        // positionally-appended arrays), which desyncs placeholders from
        // values for every remaining bound where clause. Track and drop the
        // current_stage_id binding's value alongside its where entry so the
        // two arrays stay in sync.
        $removedBindingValues = [];
        $baseQuery->wheres = array_values(array_filter(
            $baseQuery->wheres,
            function ($where) use (&$removedBindingValues) {
                if (($where['column'] ?? null) === 'engine_requests.current_stage_id') {
                    if (array_key_exists('value', $where)) {
                        $removedBindingValues[] = $where['value'];
                    }

                    return false;
                }

                return true;
            },
        ));

        if ($removedBindingValues !== []) {
            $bindings = $baseQuery->bindings['where'] ?? [];
            foreach ($removedBindingValues as $removedValue) {
                $index = array_search($removedValue, $bindings, true);
                if ($index !== false) {
                    unset($bindings[$index]);
                }
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
            // The outer merge query re-sorts by these same columns (by their
            // unqualified alias) once the per-branch results are UNION ALL'd
            // together, so each sort column must be projected here, not just
            // id, or the outer ORDER BY has nothing to sort on.
            $branch->select('engine_requests.id');
            foreach ($sortSpec as [$column, $direction]) {
                $branch->addSelect($column.' as '.last(explode('.', $column)));
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

        // DB-001/DB-002 FIELD() portability fix: the brief's original Step 3
        // used `ORDER BY FIELD(id, ...)` to restore the merged SQL order
        // during hydration, but FIELD() is MySQL-only and this repo's test
        // suite runs against SQLite. $ids is already in the correct final
        // order (sorted+limited entirely in SQL above), so instead of an
        // ORDER BY FIELD() expression we re-order the already-fetched,
        // page-sized Eloquent collection in PHP via an array_flip lookup.
        // This is cheap (at most $perPage rows) and portable across both
        // database drivers.
        $items = $modelClass::query()->whereIn('id', $ids)->get();
        $idOrder = array_flip($ids);
        $items = $items->sortBy(fn ($item) => $idOrder[$item->id])->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }
}

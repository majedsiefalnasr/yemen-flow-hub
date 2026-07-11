<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\EngineRequest;
use App\Models\User;
use App\Support\EngineRequestListQuery;
use App\Support\UnionStagePaginator;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The single source of a user's actionable ("my work") queue: the ACTIVE engine
 * requests currently sitting on a stage the user may EXECUTE, scoped by
 * DataScope. Every "current work" surface — the /my-queue endpoint, the work
 * dashboard's actionable count and preview, and the navigation actionable badge —
 * must resolve through this one contract so all of them describe the same record
 * set (Phase D0). No role codes and no hard-coded stage codes: membership is
 * derived purely from stage permissions + workflow metadata + authorization.
 */
class UserActionableRequestQuery
{
    public function __construct(
        private StagePermissionResolver $permissionResolver,
        private EngineRequestListQuery $listQuery,
    ) {}

    /**
     * Stage IDs the user may EXECUTE (their actionable stages).
     *
     * @return list<int>
     */
    public function actionableStageIds(User $user): array
    {
        $user->loadMissing('roles');

        return $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::EXECUTE);
    }

    /**
     * A single-stage branch query: ACTIVE + DataScope-scoped + list filters, for
     * exactly one accessible stage ID. Matches the UnionStagePaginator
     * branch-factory contract (one Basic where on current_stage_id, no
     * orderBy/limit/select). Includes withStageEntry() for the SLA projection the
     * paginator's ordering needs.
     *
     * @return Closure(int): Builder
     */
    public function branchFactory(User $user, Request $request): Closure
    {
        return function (int $stageId) use ($user, $request): Builder {
            $query = EngineRequest::query()
                ->withStageEntry()
                ->active()
                ->forUser($user)
                ->where('engine_requests.current_stage_id', $stageId);
            $this->listQuery->applyFilters($query, $request);

            return $query;
        };
    }

    /**
     * The same scoped set as branchFactory but WITHOUT the withStageEntry()
     * projection, for counting. Aggregating over withStageEntry()'s
     * `stage_entered_at` correlated-subquery select miscounts on SQLite (the SLA
     * projection is only needed for ordering, never for a count). The
     * withStageEntry join is re-added only when an sla_status filter requires
     * current_stage.* in its WHERE.
     */
    private function countBranch(User $user, Request $request, int $stageId): Builder
    {
        $query = EngineRequest::query()
            ->active()
            ->forUser($user)
            ->where('engine_requests.current_stage_id', $stageId);

        if ($request->filled('sla_status')) {
            $query->withStageEntry();
        }
        $this->listQuery->applyFilters($query, $request);

        return $query->select('engine_requests.id');
    }

    /**
     * The paginated دوري my-queue: SLA-priority ordering across all actionable
     * stages. This is the exact query /my-queue serves.
     */
    public function paginate(User $user, Request $request): LengthAwarePaginator
    {
        return UnionStagePaginator::paginate(
            $this->branchFactory($user, $request),
            $this->actionableStageIds($user),
            [...EngineRequest::slaOrderSpec(), ['engine_requests.id', 'asc']],
            page: $request->integer('page', 1),
            perPage: $this->listQuery->perPage($request),
            forceIndex: 'er_stage_sla_deadline',
        );
    }

    /**
     * Total actionable requests across all of the user's EXECUTE stages. Derived
     * from the same branch queries as paginate()/preview() so the count can never
     * drift from the record set it summarizes.
     */
    public function actionableCount(User $user, Request $request): int
    {
        $total = 0;
        foreach ($this->actionableStageIds($user) as $stageId) {
            $total += $this->countBranch($user, $request, $stageId)->count();
        }

        return $total;
    }

    /**
     * A standalone Request carrying the caller's query params plus the given
     * overrides. `clone $request` is a shallow copy that shares the underlying
     * ParameterBag objects, so a subsequent merge() would mutate the caller's
     * request in place and leak (e.g. a `claimed` filter) into later count/preview
     * calls. Rebuilding via Request::create() gives an isolated bag.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function withOverrides(Request $request, array $overrides): Request
    {
        return Request::create(
            $request->url(),
            'GET',
            array_merge($request->query(), $overrides),
        );
    }

    /**
     * A bounded preview of actionable requests in SLA-priority order — the same
     * ordering and record set as /my-queue, limited for a dashboard card. Never
     * loads a full queue.
     *
     * @return Collection<int, EngineRequest>
     */
    public function actionablePreview(User $user, Request $request, int $limit = 10): Collection
    {
        $preview = $this->withOverrides($request, ['per_page' => $limit, 'page' => 1]);

        return $this->paginate($user, $preview)->getCollection();
    }

    /**
     * Stages the user may VIEW but not EXECUTE — their tracking / read-only work.
     * VIEW is a superset of EXECUTE, so tracking = VIEW stages minus EXECUTE
     * stages. These records must never be counted as actionable.
     *
     * @return list<int>
     */
    public function trackingStageIds(User $user): array
    {
        $user->loadMissing('roles');
        $view = $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW);
        $execute = $this->actionableStageIds($user);

        return array_values(array_diff($view, $execute));
    }

    /**
     * A single-stage tracking branch: ACTIVE + DataScope-scoped, for one
     * VIEW-only stage. Same scoping as the actionable branch minus the EXECUTE
     * requirement (which is enforced by the stage-id set, not by the query).
     */
    private function trackingQuery(User $user, Request $request, bool $withProjection = true): Builder
    {
        $stageIds = $this->trackingStageIds($user);
        $query = EngineRequest::query()
            ->active()
            ->forUser($user)
            ->whereIn('engine_requests.current_stage_id', $stageIds === [] ? [-1] : $stageIds);
        // The stage-entry projection is only needed to render rows, not to count
        // them (it miscounts on SQLite — see countBranch()).
        if ($withProjection) {
            $query->withStageEntry();
        }
        $this->listQuery->applyFilters($query, $request);

        return $query;
    }

    public function trackingCount(User $user, Request $request): int
    {
        return $this->trackingQuery($user, $request, withProjection: false)
            ->select('engine_requests.id')->count();
    }

    /**
     * @return Collection<int, EngineRequest>
     */
    public function trackingPreview(User $user, Request $request, int $limit = 10): Collection
    {
        return $this->trackingQuery($user, $request)
            ->orderByDesc('engine_requests.updated_at')
            ->orderBy('engine_requests.id')
            ->limit($limit)
            ->get();
    }

    /**
     * Actionable requests the user currently holds a claim on.
     */
    public function claimedCount(User $user, Request $request): int
    {
        $total = 0;
        foreach ($this->actionableStageIds($user) as $stageId) {
            $total += $this->countBranch($user, $request, $stageId)
                ->where('engine_requests.claimed_by', $user->id)->count();
        }

        return $total;
    }

    /**
     * @return Collection<int, EngineRequest>
     */
    public function claimedPreview(User $user, Request $request, int $limit = 10): Collection
    {
        $preview = $this->withOverrides($request, ['per_page' => $limit, 'page' => 1, 'claimed' => 'claimed']);

        return $this->paginate($user, $preview)
            ->getCollection()
            ->where('claimed_by', $user->id)
            ->values();
    }

    /**
     * SLA pressure across the actionable set: how many are within the nearing
     * window vs already breached. Uses the same per-stage branch queries so the
     * counts describe the actionable records, not a different bucket.
     *
     * @return array{near_due: int, overdue: int}
     */
    public function slaCounts(User $user, Request $request): array
    {
        $nearDue = 0;
        $overdue = 0;
        foreach ($this->actionableStageIds($user) as $stageId) {
            $nearDue += $this->slaCountBranch($user, $request, $stageId, 'nearing');
            $overdue += $this->slaCountBranch($user, $request, $stageId, 'breached');
        }

        return ['near_due' => $nearDue, 'overdue' => $overdue];
    }

    /**
     * Count one stage's actionable requests in the given SLA window. The
     * withStageEntry() join supplies current_stage.* for applySlaStatusFilter's
     * WHERE, but the SELECT is reset to engine_requests.id so the count is not
     * taken over the SLA projection (which miscounts on SQLite — see
     * countBranch()).
     */
    private function slaCountBranch(User $user, Request $request, int $stageId, string $slaStatus): int
    {
        $query = EngineRequest::query()
            ->withStageEntry()
            ->active()
            ->forUser($user)
            ->where('engine_requests.current_stage_id', $stageId);
        $this->listQuery->applyFilters($query, $request);
        $this->listQuery->applySlaStatusFilter($query, $slaStatus);

        return $query->count('engine_requests.id');
    }
}

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
     * orderBy/limit/select).
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
        $branchFactory = $this->branchFactory($user, $request);
        $total = 0;
        foreach ($this->actionableStageIds($user) as $stageId) {
            $total += $branchFactory($stageId)->count();
        }

        return $total;
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
        $preview = clone $request;
        $preview->merge(['per_page' => $limit, 'page' => 1]);

        return $this->paginate($user, $preview)->getCollection();
    }
}

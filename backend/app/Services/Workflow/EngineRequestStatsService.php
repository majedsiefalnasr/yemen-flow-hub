<?php

namespace App\Services\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\EngineRequest;
use App\Models\User;
use App\Support\EngineRequestListQuery;
use App\Support\RoleCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EngineRequestStatsService
{
    public function __construct(
        private EngineRequestListQuery $listQuery,
        private StagePermissionResolver $permissionResolver,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     breached_sla: int,
     *     nearing_sla: int,
     *     unclaimed_active: int,
     *     by_status: array<string, int>
     * }
     */
    public function aggregate(User $user, Request $request, string $scope): array
    {
        $query = $this->buildScopedQuery($user, $request, $scope);
        $metricsQuery = $this->buildScopedQuery($user, $this->withoutSlaStatus($request), $scope);

        $total = (clone $query)->count();
        $active = (clone $query)->where('engine_requests.status', 'ACTIVE')->count();
        $breachedSla = (clone $metricsQuery)->tap(
            fn (Builder $q) => $this->listQuery->applySlaStatusFilter($q, 'breached'),
        )->count();
        $nearingSla = (clone $metricsQuery)->tap(
            fn (Builder $q) => $this->listQuery->applySlaStatusFilter($q, 'nearing'),
        )->count();
        $unclaimedActive = (clone $query)
            ->where('engine_requests.status', 'ACTIVE')
            ->whereNull('engine_requests.claimed_by')
            ->count();

        $byStatus = (clone $query)
            ->selectRaw('engine_requests.status, COUNT(*) as c')
            ->groupBy('engine_requests.status')
            ->pluck('c', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'total' => $total,
            'active' => $active,
            'breached_sla' => $breachedSla,
            'nearing_sla' => $nearingSla,
            'unclaimed_active' => $unclaimedActive,
            'by_status' => $byStatus,
        ];
    }

    private function buildScopedQuery(User $user, Request $request, string $scope): Builder
    {
        $user->loadMissing('roles');
        $accessLevel = $scope === 'queue' ? StageAccessLevel::EXECUTE : StageAccessLevel::VIEW;
        $accessibleStageIds = $this->permissionResolver->accessibleStageIds($user, $accessLevel);

        $query = EngineRequest::query()->withStageEntry();

        if ($scope === 'queue') {
            $query->active()->forUser($user)->whereIn('engine_requests.current_stage_id', $accessibleStageIds);
        } elseif (! $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            $query->forUser($user)->whereIn('engine_requests.current_stage_id', $accessibleStageIds);
        }

        $this->listQuery->applyFilters($query, $request);

        return $query;
    }

    private function withoutSlaStatus(Request $request): Request
    {
        $query = collect($request->query())->except('sla_status')->all();

        return Request::create($request->url(), $request->method(), $query);
    }
}

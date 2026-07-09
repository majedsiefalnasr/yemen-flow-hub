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

        // API-002: one grouped pass yields by_status, total, active, and
        // unclaimed_active together, replacing four separate COUNT scans. The two
        // SLA metrics stay separate because applySlaStatusFilter changes the row
        // set (a derived SLA window, not a status bucket) and can't be read off
        // the status grouping.
        $statusRows = (clone $query)
            ->selectRaw('engine_requests.status')
            ->selectRaw('COUNT(*) as c')
            ->selectRaw('SUM(CASE WHEN engine_requests.claimed_by IS NULL THEN 1 ELSE 0 END) as unclaimed')
            ->groupBy('engine_requests.status')
            ->get();

        $byStatus = $statusRows
            ->mapWithKeys(fn ($row) => [$row->status => (int) $row->c])
            ->all();
        $total = (int) $statusRows->sum('c');
        $active = (int) ($byStatus['ACTIVE'] ?? 0);
        $unclaimedActive = (int) ($statusRows->firstWhere('status', 'ACTIVE')?->unclaimed ?? 0);

        $breachedSla = (clone $metricsQuery)->tap(
            fn (Builder $q) => $this->listQuery->applySlaStatusFilter($q, 'breached'),
        )->count();
        $nearingSla = (clone $metricsQuery)->tap(
            fn (Builder $q) => $this->listQuery->applySlaStatusFilter($q, 'nearing'),
        )->count();

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

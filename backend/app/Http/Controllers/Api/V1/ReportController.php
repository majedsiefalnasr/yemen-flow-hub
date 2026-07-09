<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Models\EngineRequest;
use App\Models\WorkflowHistoryEntry;
use App\Services\Authorization\DataScope;
use App\Services\Authorization\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function summary(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        // API-005 (perf audit): one grouped pass instead of seven full scans.
        // Aggregates per-status counts + the amount sum in a single query, then
        // derives the individual status buckets in PHP.
        $rows = $this->baseQuery($request)
            ->selectRaw('engine_requests.status, COUNT(*) as c, SUM(engine_requests.amount) as amt')
            ->groupBy('engine_requests.status')
            ->get();

        $counts = $rows->pluck('c', 'status');
        $total = (int) $rows->sum('c');
        $active = (int) ($counts['ACTIVE'] ?? 0);
        $closed = (int) ($counts['CLOSED'] ?? 0);
        $rejected = (int) ($counts['REJECTED'] ?? 0);
        $cancelled = (int) ($counts['CANCELLED'] ?? 0);
        $abandoned = (int) ($counts['ABANDONED'] ?? 0);
        $totalAmount = (float) $rows->sum('amt');

        return response()->json(['data' => compact('total', 'active', 'closed', 'rejected', 'cancelled', 'abandoned', 'totalAmount')]);
    }

    public function requestsOverTime(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = $this->baseQuery($request);
        $monthFormat = $this->monthFormat();

        // Take the most recent 24 months (order desc + limit), then reverse to ascending
        // so the trend chart reads oldest → newest without dropping recent data.
        $rows = $query
            ->selectRaw("{$monthFormat} as month, COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN engine_requests.status = 'CLOSED' THEN 1 ELSE 0 END) as closed")
            ->selectRaw("SUM(CASE WHEN engine_requests.status = 'REJECTED' THEN 1 ELSE 0 END) as rejected")
            ->groupByRaw($monthFormat)
            ->orderByDesc('month')
            ->limit(24)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'month' => $r->month,
                'total' => (int) $r->total,
                'closed' => (int) $r->closed,
                'rejected' => (int) $r->rejected,
            ]),
        ]);
    }

    public function byWorkflowStage(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = $this->baseQuery($request);

        $rows = $query
            ->leftJoin('workflow_stages as ws', 'ws.id', '=', 'engine_requests.current_stage_id')
            ->selectRaw("COALESCE(ws.code, 'unassigned') as stage_code, COALESCE(ws.name, 'بدون مرحلة') as stage_name, COUNT(*) as count")
            ->groupByRaw("COALESCE(ws.code, 'unassigned'), COALESCE(ws.name, 'بدون مرحلة')")
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'stage_code' => $r->stage_code,
                'stage_name' => $r->stage_name,
                'count' => (int) $r->count,
            ]),
        ]);
    }

    public function byBank(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = $this->baseQuery($request);

        $rows = $query
            ->leftJoin('banks as b', 'b.id', '=', 'engine_requests.bank_id')
            ->selectRaw("b.id as bank_id, COALESCE(b.name, 'بدون بنك') as bank_name, COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN engine_requests.status = 'CLOSED' THEN 1 ELSE 0 END) as closed")
            ->selectRaw("SUM(CASE WHEN engine_requests.status = 'REJECTED' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw('SUM(engine_requests.amount) as total_amount')
            ->groupByRaw("b.id, COALESCE(b.name, 'بدون بنك')")
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'bank_id' => $r->bank_id,
                'bank_name' => $r->bank_name,
                'total' => (int) $r->total,
                'closed' => (int) $r->closed,
                'rejected' => (int) $r->rejected,
                'total_amount' => (float) ($r->total_amount ?? 0),
            ]),
        ]);
    }

    public function byMerchant(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = $this->baseQuery($request);

        $rows = $query
            ->leftJoin('merchants as m', 'm.id', '=', 'engine_requests.merchant_id')
            ->selectRaw("m.id as merchant_id, COALESCE(m.name, 'بدون تاجر') as merchant_name, COUNT(*) as total")
            ->selectRaw('SUM(engine_requests.amount) as total_amount')
            ->groupByRaw("m.id, COALESCE(m.name, 'بدون تاجر')")
            ->orderByDesc('total')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'merchant_id' => $r->merchant_id,
                'merchant_name' => $r->merchant_name,
                'total' => (int) $r->total,
                'total_amount' => (float) ($r->total_amount ?? 0),
            ]),
        ]);
    }

    public function bySector(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = $this->baseQuery($request);

        $rows = $query
            ->leftJoin('merchants as m', 'm.id', '=', 'engine_requests.merchant_id')
            ->selectRaw("COALESCE(m.business_type, 'Uncategorized') as sector, COUNT(*) as count")
            ->selectRaw('SUM(engine_requests.amount) as total_amount')
            ->groupByRaw("COALESCE(m.business_type, 'Uncategorized')")
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'sector' => $r->sector,
                'count' => (int) $r->count,
                'total_amount' => (float) ($r->total_amount ?? 0),
            ]),
        ]);
    }

    public function byCurrency(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = $this->baseQuery($request);

        $rows = $query
            ->selectRaw("COALESCE(engine_requests.currency, 'N/A') as currency, COUNT(*) as count")
            ->selectRaw('SUM(engine_requests.amount) as total_amount')
            ->groupByRaw("COALESCE(engine_requests.currency, 'N/A')")
            ->orderByDesc('total_amount')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'currency' => $r->currency,
                'count' => (int) $r->count,
                'total_amount' => (float) ($r->total_amount ?? 0),
            ]),
        ]);
    }

    public function stageDuration(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $driver = DB::connection()->getDriverName();
        $hoursDiff = $driver === 'sqlite'
            ? '(julianday(h2.created_at) - julianday(h1.created_at)) * 24'
            : 'TIMESTAMPDIFF(HOUR, h1.created_at, h2.created_at)';

        $scope = DataScope::forUser($request->user());

        $rows = DB::table('workflow_history as h1')
            ->join('workflow_stages as ws', 'ws.id', '=', 'h1.to_stage_id')
            ->join('engine_requests as er', 'er.id', '=', 'h1.request_id')
            ->join(DB::raw('workflow_history h2'), function ($join) {
                $join->on('h2.request_id', '=', 'h1.request_id')
                    ->whereRaw('h2.id = (SELECT MIN(h3.id) FROM workflow_history h3 WHERE h3.request_id = h1.request_id AND h3.created_at > h1.created_at)');
            })
            ->when(! $scope->systemWide, function ($q) use ($scope) {
                if ($scope->ownBankId !== null) {
                    return $q->where('er.bank_id', $scope->ownBankId);
                }

                return $q->whereRaw('1 = 0');
            })
            // API-007: half-open range bounds instead of whereDate().
            ->when($request->filled('from'), fn ($q) => $q->where('h1.created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('h1.created_at', '<', $request->date('to')->addDay()->startOfDay()))
            ->when($request->filled('bank'), fn ($q) => $q->where('er.bank_id', $request->integer('bank')))
            ->when($request->filled('version'), fn ($q) => $q->where('er.workflow_version_id', $request->integer('version')))
            ->when($request->filled('status'), fn ($q) => $q->where('er.status', $request->string('status')))
            ->when($request->filled('currency'), fn ($q) => $q->where('er.currency', $request->string('currency')))
            ->selectRaw("ws.code as stage_code, ws.name as stage_name, AVG({$hoursDiff}) as avg_hours, COUNT(*) as transitions")
            ->groupBy('ws.code', 'ws.name')
            ->orderBy('ws.code')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'stage_code' => $r->stage_code,
                'stage_name' => $r->stage_name,
                'avg_hours' => round((float) $r->avg_hours, 2),
                'transitions' => (int) $r->transitions,
            ]),
        ]);
    }

    public function sla(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = EngineRequest::query()
            ->withStageEntry()
            ->whereNotNull('current_stage.sla_duration_minutes')
            ->with('currentStage:id,code,name,sla_duration_minutes');

        $this->applyScope($request, $query);
        $this->applyFilters($request, $query);

        $requests = $query->get();
        $grouped = $requests->groupBy(fn ($r) => $r->currentStage?->code ?? 'unknown');

        $data = $grouped->map(function ($items, $stageCode) {
            $total = $items->count();
            $breached = $items->filter(fn ($r) => $r->sla_status === 'breached')->count();
            $nearing = $items->filter(fn ($r) => $r->sla_status === 'nearing')->count();
            $stageName = $items->first()?->currentStage?->name ?? $stageCode;

            return [
                'stage_code' => $stageCode,
                'stage_name' => $stageName,
                'total' => $total,
                'breached' => $breached,
                'nearing' => $nearing,
                'ok' => $total - $breached - $nearing,
                'breach_rate' => $total > 0 ? round(($breached / $total) * 100, 2) : 0,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function teamPerformance(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $query = WorkflowHistoryEntry::query()
            ->join('users as u', 'u.id', '=', 'workflow_history.performed_by')
            ->leftJoin('user_roles as ur', function ($join) {
                $join->on('ur.user_id', '=', 'u.id')
                    ->where('ur.is_active', true);
            })
            ->leftJoin('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('engine_requests', 'engine_requests.id', '=', 'workflow_history.request_id')
            // COUNT(DISTINCT workflow_history.id) — the user_roles fan-out would otherwise
            // multiply each action by the number of roles the performing user holds.
            ->selectRaw("COALESCE(r.name, 'Unknown') as role_name, COUNT(DISTINCT workflow_history.id) as actions")
            ->selectRaw('COUNT(DISTINCT workflow_history.performed_by) as members')
            ->groupByRaw("COALESCE(r.name, 'Unknown')")
            ->orderByDesc('actions');

        $this->applyScope($request, $query);
        $this->applyFilters($request, $query);

        $rows = $query->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'role' => $r->role_name,
                'actions' => (int) $r->actions,
                'members' => (int) $r->members,
                'avg_actions_per_member' => $r->members > 0 ? round($r->actions / $r->members, 1) : 0,
            ]),
        ]);
    }

    private function baseQuery(Request $request): Builder
    {
        $query = EngineRequest::query();
        $this->applyScope($request, $query);
        $this->applyFilters($request, $query);

        return $query;
    }

    private function applyScope(Request $request, $query): void
    {
        $scope = DataScope::forUser($request->user());
        DataScope::applyTo($query, $scope, 'engine_requests.bank_id');
    }

    private function applyFilters(Request $request, $query): void
    {
        // API-007: half-open range bounds instead of whereDate() — wrapping
        // created_at in DATE() defeats any index on the column.
        if ($request->filled('from')) {
            $query->where('engine_requests.created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('engine_requests.created_at', '<', $request->date('to')->addDay()->startOfDay());
        }
        if ($request->filled('workflow')) {
            $query->whereHas('workflowVersion', fn ($q) => $q->where('workflow_definition_id', $request->integer('workflow')));
        }
        if ($request->filled('version')) {
            $query->where('engine_requests.workflow_version_id', $request->integer('version'));
        }
        if ($request->filled('bank')) {
            $query->where('engine_requests.bank_id', $request->integer('bank'));
        }
        if ($request->filled('stage')) {
            $query->where('engine_requests.current_stage_id', $request->integer('stage'));
        }
        if ($request->filled('status')) {
            $query->where('engine_requests.status', $request->string('status'));
        }
        if ($request->filled('currency')) {
            $query->where('engine_requests.currency', $request->string('currency'));
        }
    }

    private function monthFormat(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', engine_requests.created_at)"
            : "DATE_FORMAT(engine_requests.created_at, '%Y-%m')";
    }
}

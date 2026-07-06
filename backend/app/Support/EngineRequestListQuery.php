<?php

namespace App\Support;

use App\Http\Resources\EngineRequestResource;
use App\Models\EngineRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineRequestListQuery
{
    private const ALLOWED_STATUSES = ['ACTIVE', 'CLOSED', 'REJECTED'];

    private const ALLOWED_SLA_STATUSES = ['ok', 'nearing', 'breached'];

    public function applyFilters($query, Request $request): void
    {
        if ($request->filled('workflow_id')) {
            $query->whereHas('workflowVersion', fn ($q) => $q->where('workflow_definition_id', $request->integer('workflow_id')));
        }
        if ($request->filled('workflow_version_id')) {
            $query->where('engine_requests.workflow_version_id', $request->integer('workflow_version_id'));
        }
        if ($request->filled('stage_id')) {
            $query->where('engine_requests.current_stage_id', $request->integer('stage_id'));
        }
        if ($request->filled('bank_id')) {
            $query->where('engine_requests.bank_id', $request->integer('bank_id'));
        }
        if ($request->filled('merchant_id')) {
            $query->where('engine_requests.merchant_id', $request->integer('merchant_id'));
        }
        if ($request->filled('status') && in_array($request->string('status')->value(), self::ALLOWED_STATUSES, true)) {
            $query->where('engine_requests.status', $request->string('status'));
        }
        if ($request->filled('created_from')) {
            $query->whereDate('engine_requests.created_at', '>=', $request->date('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('engine_requests.created_at', '<=', $request->date('created_to'));
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q
                ->where('engine_requests.reference', 'like', $term)
                ->orWhere('engine_requests.invoice_number', 'like', $term));
        }
        if ($request->filled('sla_status') && in_array($request->string('sla_status')->value(), self::ALLOWED_SLA_STATUSES, true)) {
            $this->applySlaStatusFilter($query, $request->string('sla_status')->value());
        }
    }

    public function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 25)));
    }

    public function paginatedResponse($page): JsonResponse
    {
        return response()->json([
            'data' => EngineRequestResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Filters on derived SLA status using the stage-entry subselect + stage SLA window,
     * never a JSON scan. `ok` and `nearing` exclude requests with no SLA configured.
     * Expressions are epoch-second based so they run on both MySQL and SQLite.
     */
    private function applySlaStatusFilter($query, string $slaStatus): void
    {
        $deadline = EngineRequest::slaDeadlineEpochSql();
        $now = EngineRequest::nowEpochSql();
        // Nearing window = the final 20% of the SLA (at least 1 minute) before the deadline.
        $nearingWindow = 'MAX(1, CAST(current_stage.sla_duration_minutes * 0.2 AS INTEGER)) * 60';
        $threshold = "({$deadline}) - ({$nearingWindow})";

        match ($slaStatus) {
            'breached' => $query->whereNotNull('current_stage.sla_duration_minutes')
                ->whereRaw("({$deadline}) < ({$now})"),
            'nearing' => $query->whereNotNull('current_stage.sla_duration_minutes')
                ->whereRaw("({$deadline}) >= ({$now})")
                ->whereRaw("({$now}) >= ({$threshold})"),
            'ok' => $query->whereNotNull('current_stage.sla_duration_minutes')
                ->whereRaw("({$now}) < ({$threshold})"),
            default => null,
        };
    }
}

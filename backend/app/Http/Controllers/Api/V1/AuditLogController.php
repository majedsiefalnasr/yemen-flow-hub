<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Authorization\DataScopeContext;
use App\Enums\AuditAction;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\V1\AuditLogResource;
use App\Models\AuditLog;
use App\Services\Audit\AuditService;
use App\Services\Authorization\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $scope = DataScope::forUser($request->user());
        if ($request->user()->isSystemAdmin()) {
            $scope = new DataScopeContext(systemWide: true);
        }

        $query = AuditLog::query()
            ->with(['user', 'actorRole']);

        DataScope::applyTo($query, $scope);

        $query->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('role'), fn ($q) => $q->where('actor_role_id', $request->integer('role')))
            ->when($request->filled('event'), fn ($q) => $q->where('action', $request->string('event')))
            ->when(! $request->filled('event'), fn ($q) => $q->whereNotIn('action', [AuditAction::LOGIN->value, AuditAction::LOGOUT->value]))
            ->when($request->filled('entity'), fn ($q) => $q->where('subject_type', 'like', '%'.class_basename($request->string('entity')).'%'))
            ->when($request->filled('request'), fn ($q) => $q->where('workflow_instance_id', $request->integer('request')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')))
            ->when($request->filled('ip'), fn ($q) => $q->where('ip_address', $request->string('ip')))
            ->when($request->filled('correlation_id'), fn ($q) => $q->where('correlation_id', $request->string('correlation_id')))
            ->latest('id');

        $page = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => AuditLogResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        $scope = DataScope::forUser($request->user());
        if ($request->user()->isSystemAdmin()) {
            $scope = new DataScopeContext(systemWide: true);
        }

        // Ensure the specific log is within scope
        if (! $scope->systemWide) {
            // Audit logs don't have bank_id yet, so for now non-systemWide users see nothing.
            // This matches the policy-level restriction.
            abort(403);
        }

        $auditLog->load(['user', 'actorRole', 'engineRequest']);

        return response()->json([
            'data' => new AuditLogResource($auditLog),
        ]);
    }

    public function export(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $scope = DataScope::forUser($request->user());
        if ($request->user()->isSystemAdmin()) {
            $scope = new DataScopeContext(systemWide: true);
        }

        $query = AuditLog::query()
            ->with(['user', 'actorRole']);

        DataScope::applyTo($query, $scope);

        $query->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('role'), fn ($q) => $q->where('actor_role_id', $request->integer('role')))
            ->when($request->filled('event'), fn ($q) => $q->where('action', $request->string('event')))
            ->when($request->filled('entity'), fn ($q) => $q->where('subject_type', 'like', '%'.class_basename($request->string('entity')).'%'))
            ->when($request->filled('request'), fn ($q) => $q->where('workflow_instance_id', $request->integer('request')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')))
            ->when($request->filled('ip'), fn ($q) => $q->where('ip_address', $request->string('ip')))
            ->when($request->filled('correlation_id'), fn ($q) => $q->where('correlation_id', $request->string('correlation_id')))
            ->latest('id')
            ->limit(10000);

        $rows = $query->get();

        $this->auditService->log(AuditAction::AUDIT_LOG_EXPORTED, $request->user(), null, [
            'row_count' => $rows->count(),
            'filters' => $request->only(['user', 'role', 'event', 'entity', 'request', 'from', 'to', 'ip', 'correlation_id']),
        ]);

        $csv = "\xEF\xBB\xBF".implode(',', ['ID', 'User', 'Role', 'Event', 'Entity', 'IP', 'Timestamp'])."\n";
        foreach ($rows as $row) {
            $csv .= implode(',', [
                $row->id,
                $this->csvCell($row->user?->name ?? ''),
                $this->csvCell($row->user_role ?? ''),
                $this->csvCell($row->action),
                $this->csvCell(($row->subject_type ? class_basename($row->subject_type) : '').':'.($row->subject_id ?? '')),
                $this->csvCell($row->ip_address ?? ''),
                $this->csvCell($row->created_at?->toISOString() ?? ''),
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-logs-export.csv"',
        ]);
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 30)));
    }

    private function csvCell(string $value): string
    {
        // Neutralize CSV formula injection: a cell that a spreadsheet would evaluate as a
        // formula gets prefixed with a single quote so it is rendered as literal text.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'".$value;
        }

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}

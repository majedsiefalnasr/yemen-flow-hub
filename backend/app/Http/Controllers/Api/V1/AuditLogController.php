<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Authorization\DataScopeContext;
use App\Enums\AuditAction;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\V1\AuditLogResource;
use App\Jobs\GenerateAuditLogExport;
use App\Models\AuditLog;
use App\Models\ReportExport;
use App\Services\Audit\AuditService;
use App\Services\Authorization\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            ->when($request->filled('entity'), fn ($q) => $q->where('subject_type', $request->string('entity')))
            ->when($request->filled('request'), fn ($q) => $q->where('workflow_instance_id', $request->integer('request')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<', $request->date('to')->addDay()->startOfDay()))
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

        // SEC-002: a bank-scoped user may only view a log within their own
        // bank; a log with no bank (CBY-only entity) or another bank's log
        // is out of scope even though viewAny() already passed.
        if (! $scope->systemWide) {
            if ($scope->ownBankId === null || $auditLog->bank_id !== $scope->ownBankId) {
                abort(403);
            }
        }

        $auditLog->load(['user', 'actorRole', 'engineRequest']);

        return response()->json([
            'data' => new AuditLogResource($auditLog),
        ]);
    }

    /**
     * API-004: async export. Creates a ReportExport row (report_type
     * 'audit-logs', reusing the same polling/download flow as
     * ReportExportController) and dispatches GenerateAuditLogExport instead of
     * building the CSV synchronously in the request — audit_logs is one of
     * the largest, unbounded-growth tables (ARCH-006).
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->only(['user', 'role', 'event', 'entity', 'request', 'from', 'to', 'ip', 'correlation_id']);

        $export = ReportExport::create([
            'requested_by' => $request->user()->id,
            'report_type' => 'audit-logs',
            'filters' => $filters,
            'format' => 'csv',
            'status' => 'PENDING',
        ]);

        $this->auditService->log(AuditAction::REPORT_EXPORT_CREATED, $request->user(), null, [
            'export_id' => $export->id,
            'report_type' => 'audit-logs',
            'filters' => $filters,
        ]);

        GenerateAuditLogExport::dispatch($export->id);

        return response()->json([
            'data' => [
                'id' => $export->id,
                'report_type' => $export->report_type,
                'filters' => $export->filters,
                'format' => $export->format,
                'status' => $export->status,
                'created_at' => $export->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Poll endpoint for the async export's status, scoped to the requesting
     * user's own export and the audit-logs report type.
     */
    public function showExport(Request $request, ReportExport $reportExport): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);
        $this->guardAuditExportOwnership($request, $reportExport);

        return response()->json(['data' => $this->exportResource($reportExport)]);
    }

    public function downloadExport(Request $request, ReportExport $reportExport): mixed
    {
        $this->authorize('viewAny', AuditLog::class);
        $this->guardAuditExportOwnership($request, $reportExport);

        if ($reportExport->status === 'FAILED') {
            return response()->json([
                'error' => ['code' => 'EXPORT_FAILED', 'message' => 'Export failed and is not available for download.'],
            ], 422);
        }

        if ($reportExport->status !== 'COMPLETED' || $reportExport->file_path === null) {
            return response()->json([
                'error' => ['code' => 'EXPORT_NOT_READY', 'message' => 'Export is not yet completed.'],
            ], 422);
        }

        if (! Storage::disk('private')->exists($reportExport->file_path)) {
            abort(404);
        }

        $this->auditService->log(AuditAction::REPORT_EXPORT_DOWNLOADED, $request->user(), null, [
            'export_id' => $reportExport->id,
            'report_type' => 'audit-logs',
        ]);

        return Storage::disk('private')->download(
            $reportExport->file_path,
            "audit-logs-export-{$reportExport->id}.csv",
        );
    }

    private function guardAuditExportOwnership(Request $request, ReportExport $reportExport): void
    {
        abort_unless($reportExport->report_type === 'audit-logs', 404);
        abort_unless((int) $reportExport->requested_by === (int) $request->user()->id, 403);
    }

    private function exportResource(ReportExport $export): array
    {
        return [
            'id' => $export->id,
            'report_type' => $export->report_type,
            'filters' => $export->filters,
            'format' => $export->format,
            'status' => $export->status,
            'total_matching' => $export->total_matching,
            'exported_count' => $export->exported_count,
            'truncated' => (bool) $export->truncated,
            'truncation_note' => $export->truncation_note,
            'created_at' => $export->created_at?->toISOString(),
        ];
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 30)));
    }
}

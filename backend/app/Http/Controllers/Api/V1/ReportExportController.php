<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Http\Controllers\Api\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Services\Audit\AuditService;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportExportController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly AuditService $auditService
    ) {}

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'EXPORT'), 403);

        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:summary,requests-over-time,by-workflow-stage,by-bank,by-merchant,by-sector,by-currency,stage-duration,sla,team-performance'],
            'filters' => ['nullable', 'array'],
            // PDF export is not implemented yet; only CSV is accepted so the stored
            // format never disagrees with the file the job actually writes.
            'format' => ['nullable', 'string', 'in:csv'],
        ]);

        $export = ReportExport::create([
            'requested_by' => $request->user()->id,
            'report_type' => $validated['report_type'],
            'filters' => $validated['filters'] ?? [],
            'format' => $validated['format'] ?? 'csv',
            'status' => 'PENDING',
        ]);

        $this->auditService->log(AuditAction::REPORT_EXPORT_CREATED, $request->user(), null, [
            'export_id' => $export->id,
            'report_type' => $export->report_type,
            'filters' => $export->filters,
            'format' => $export->format,
            'organization_id' => $request->user()->organization_id,
            'classification' => $request->user()->organization?->classification,
        ]);

        GenerateReportExport::dispatch($export->id);

        return response()->json([
            'data' => $this->resource($export),
        ], 201);
    }

    public function show(Request $request, ReportExport $reportExport): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        if ((int) $reportExport->requested_by !== (int) $request->user()->id) {
            abort(403);
        }

        return response()->json(['data' => $this->resource($reportExport)]);
    }

    public function download(Request $request, ReportExport $reportExport): mixed
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'EXPORT'), 403);

        if ((int) $reportExport->requested_by !== (int) $request->user()->id) {
            abort(403);
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
            'report_type' => $reportExport->report_type,
            'organization_id' => $request->user()->organization_id,
            'classification' => $request->user()->organization?->classification,
        ]);

        return Storage::disk('private')->download(
            $reportExport->file_path,
            "report-{$reportExport->report_type}-{$reportExport->id}.csv",
        );
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->permissionService->userHasCapability($request->user(), 'reports', 'VIEW'), 403);

        $exports = ReportExport::query()
            ->where('requested_by', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($exports->items())->map(fn ($e) => $this->resource($e)),
            'meta' => [
                'current_page' => $exports->currentPage(),
                'last_page' => $exports->lastPage(),
                'per_page' => $exports->perPage(),
                'total' => $exports->total(),
            ],
        ]);
    }

    private function resource(ReportExport $export): array
    {
        return [
            'id' => $export->id,
            'report_type' => $export->report_type,
            'filters' => $export->filters,
            'format' => $export->format,
            'status' => $export->status,
            'created_at' => $export->created_at?->toISOString(),
        ];
    }
}

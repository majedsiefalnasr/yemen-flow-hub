<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ReportExportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('reports.view');

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

        GenerateReportExport::dispatch($export->id);

        return response()->json([
            'data' => $this->resource($export),
        ], 201);
    }

    public function show(Request $request, ReportExport $reportExport): JsonResponse
    {
        Gate::authorize('reports.view');

        if ((int) $reportExport->requested_by !== (int) $request->user()->id) {
            abort(403);
        }

        return response()->json(['data' => $this->resource($reportExport)]);
    }

    public function download(Request $request, ReportExport $reportExport): mixed
    {
        Gate::authorize('reports.view');

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

        return Storage::disk('private')->download(
            $reportExport->file_path,
            "report-{$reportExport->report_type}-{$reportExport->id}.csv",
        );
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('reports.view');

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

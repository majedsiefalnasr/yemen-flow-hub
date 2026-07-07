<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Models\EngineRequest;
use App\Models\ReportExport;
use App\Services\Audit\AuditService;
use App\Services\Authorization\DataScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ROW_LIMIT = 10000;

    public function __construct(
        private readonly int $exportId,
    ) {}

    public function handle(AuditService $auditService): void
    {
        $export = ReportExport::with(['requester.organization'])->find($this->exportId);
        if ($export === null || $export->status !== 'PENDING') {
            return;
        }

        $export->update(['status' => 'PROCESSING']);

        try {
            $filters = $export->filters ?? [];
            $query = EngineRequest::query()
                ->with(['bank:id,name', 'currentStage:id,code,name', 'merchant:id,name']);

            // Enforce the requester's data scope. The job runs detached from the request
            // auth context, so scope is re-derived from the stored requester and cannot
            // be widened by the filters payload.
            $requester = $export->requester;
            if ($requester) {
                $scope = DataScope::forUser($requester);
                DataScope::applyTo($query, $scope);
            } else {
                // If no requester, default to matching nothing for safety.
                $query->whereRaw('1 = 0');
            }

            if (! empty($filters['bank'])) {
                $query->where('bank_id', $filters['bank']);
            }
            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (! empty($filters['from'])) {
                $query->whereDate('created_at', '>=', $filters['from']);
            }
            if (! empty($filters['to'])) {
                $query->whereDate('created_at', '<=', $filters['to']);
            }
            if (! empty($filters['currency'])) {
                $query->where('currency', $filters['currency']);
            }
            if (! empty($filters['stage'])) {
                $query->where('current_stage_id', $filters['stage']);
            }
            if (! empty($filters['version'])) {
                $query->where('workflow_version_id', $filters['version']);
            }
            if (! empty($filters['workflow'])) {
                $query->whereHas('workflowVersion', fn ($q) => $q->where('workflow_definition_id', $filters['workflow']));
            }

            $totalMatching = (clone $query)->count();
            $rows = $query->orderByDesc('created_at')->limit(self::ROW_LIMIT)->get();
            $exportedCount = $rows->count();
            $truncated = $totalMatching > $exportedCount;
            $filterSummary = json_encode($filters, JSON_UNESCAPED_UNICODE) ?: '{}';

            $preamble = $truncated
                ? "# Exported {$exportedCount} of {$totalMatching} matching rows. Applied filters: {$filterSummary}. truncated: yes. Narrow filters for a complete export.\n"
                : "# Exported {$exportedCount} rows. Applied filters: {$filterSummary}. truncated: no.\n";

            $csv = "\xEF\xBB\xBF".$preamble.implode(',', [
                'ID', 'Reference', 'Bank', 'Merchant', 'Stage', 'Status',
                'Amount', 'Currency', 'Created At',
            ])."\n";

            foreach ($rows as $row) {
                $csv .= implode(',', [
                    $row->id,
                    $this->cell($row->reference ?? ''),
                    $this->cell($row->bank?->name ?? ''),
                    $this->cell($row->merchant?->name ?? ''),
                    $this->cell($row->currentStage?->name ?? ''),
                    $this->cell($row->status),
                    $row->amount ?? 0,
                    $this->cell($row->currency ?? ''),
                    $this->cell($row->created_at?->toISOString() ?? ''),
                ])."\n";
            }

            $path = "exports/report-{$export->id}.csv";
            Storage::disk('private')->put($path, $csv);

            $truncationNote = $truncated
                ? "Exported {$exportedCount} of {$totalMatching} matching rows."
                : null;

            $export->update([
                'status' => 'COMPLETED',
                'file_path' => $path,
                'total_matching' => $totalMatching,
                'exported_count' => $exportedCount,
                'truncated' => $truncated,
                'truncation_note' => $truncationNote,
            ]);

            $auditor = $export->requester;
            $auditService->log(AuditAction::REPORT_EXPORTED, $auditor, null, [
                'export_id' => $export->id,
                'report_type' => $export->report_type,
                'row_count' => $exportedCount,
                'total_matching' => $totalMatching,
                'truncated' => $truncated,
                'organization_id' => $auditor?->organization_id,
                'classification' => $auditor?->organization?->classification,
                'filters' => $export->filters,
                'format' => $export->format,
            ]);
        } catch (\Throwable $e) {
            $export->update(['status' => 'FAILED']);

            throw $e;
        }
    }

    /**
     * Final safety net: if the job exhausts its retries (or dies after marking the row
     * PROCESSING), make sure the export does not stay stuck in a non-terminal state.
     */
    public function failed(\Throwable $exception): void
    {
        $export = ReportExport::find($this->exportId);
        if ($export !== null && ! in_array($export->status, ['COMPLETED', 'FAILED'], true)) {
            $export->update(['status' => 'FAILED']);
        }
    }

    private function cell(string $value): string
    {
        // Neutralize CSV formula injection: prefix formula-trigger characters so a
        // spreadsheet renders the cell as literal text instead of evaluating it.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'".$value;
        }

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}

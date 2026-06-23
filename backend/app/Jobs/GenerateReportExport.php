<?php

namespace App\Jobs;

use App\Enums\AuditAction;
use App\Models\EngineRequest;
use App\Models\ReportExport;
use App\Services\Audit\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $exportId,
    ) {}

    public function handle(AuditService $auditService): void
    {
        $export = ReportExport::find($this->exportId);
        if ($export === null || $export->status !== 'PENDING') {
            return;
        }

        $export->update(['status' => 'PROCESSING']);

        try {
            $filters = $export->filters ?? [];
            $query = EngineRequest::query()
                ->with(['bank:id,name', 'currentStage:id,code,name', 'merchant:id,name']);

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

            $rows = $query->orderByDesc('created_at')->limit(50000)->get();

            $csv = "\xEF\xBB\xBF".implode(',', [
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

            $export->update(['status' => 'COMPLETED', 'file_path' => $path]);

            $auditor = $export->requester;
            $auditService->log(AuditAction::REPORT_EXPORTED, $auditor, null, [
                'export_id' => $export->id,
                'report_type' => $export->report_type,
                'row_count' => $rows->count(),
            ]);
        } catch (\Throwable $e) {
            $export->update(['status' => 'FAILED']);

            throw $e;
        }
    }

    private function cell(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}

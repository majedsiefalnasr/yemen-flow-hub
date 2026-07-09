<?php

namespace App\Jobs;

use App\DTOs\Authorization\DataScopeContext;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\ReportExport;
use App\Services\Audit\AuditService;
use App\Services\Authorization\DataScope;
use App\Services\Operations\OperationalAlertLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * API-004: async audit-log CSV export. Mirrors GenerateReportExport's shape
 * (scope re-derived from the stored requester, bounded row count, fail-closed
 * status on error) but streams via lazy() instead of get() — audit_logs is
 * one of the largest, unbounded-growth tables (ARCH-006), so holding all rows
 * plus relations in memory at once is the wrong lifecycle even under the row
 * cap.
 */
class GenerateAuditLogExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ROW_LIMIT = 10000;

    public int $tries = 3;

    /** Generous: up to ROW_LIMIT rows streamed with relations, CSV build, disk write. */
    public int $timeout = 300;

    public function __construct(
        private readonly int $exportId,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(AuditService $auditService): void
    {
        $export = ReportExport::with(['requester.organization'])->find($this->exportId);
        if ($export === null || $export->status !== 'PENDING') {
            return;
        }

        $export->update(['status' => 'PROCESSING']);

        try {
            $filters = $export->filters ?? [];
            $query = AuditLog::query()->with(['user']);

            $requester = $export->requester;
            if ($requester) {
                $scope = $requester->isSystemAdmin()
                    ? new DataScopeContext(systemWide: true)
                    : DataScope::forUser($requester);
                DataScope::applyTo($query, $scope);
            } else {
                $query->whereRaw('1 = 0');
            }

            if (! empty($filters['user'])) {
                $query->where('user_id', (int) $filters['user']);
            }
            if (! empty($filters['role'])) {
                $query->where('actor_role_id', (int) $filters['role']);
            }
            if (! empty($filters['event'])) {
                $query->where('action', $filters['event']);
            }
            if (! empty($filters['entity'])) {
                $query->where('subject_type', $filters['entity']);
            }
            if (! empty($filters['request'])) {
                $query->where('workflow_instance_id', (int) $filters['request']);
            }
            if (! empty($filters['from'])) {
                $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
            }
            if (! empty($filters['to'])) {
                $query->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
            }
            if (! empty($filters['ip'])) {
                $query->where('ip_address', $filters['ip']);
            }
            if (! empty($filters['correlation_id'])) {
                $query->where('correlation_id', $filters['correlation_id']);
            }

            $totalMatching = (clone $query)->count();

            $csv = "\xEF\xBB\xBF".implode(',', ['ID', 'User', 'Role', 'Event', 'Entity', 'IP', 'Timestamp'])."\n";
            $exportedCount = 0;
            foreach ($query->orderByDesc('id')->limit(self::ROW_LIMIT)->lazy() as $row) {
                $csv .= implode(',', [
                    $row->id,
                    $this->cell($row->user?->name ?? ''),
                    $this->cell($row->user_role ?? ''),
                    $this->cell($row->action),
                    $this->cell(($row->subject_type ? class_basename($row->subject_type) : '').':'.($row->subject_id ?? '')),
                    $this->cell($row->ip_address ?? ''),
                    $this->cell($row->created_at?->toISOString() ?? ''),
                ])."\n";
                $exportedCount++;
            }

            $truncated = $totalMatching > $exportedCount;
            $truncationNote = $truncated
                ? "Exported {$exportedCount} of {$totalMatching} matching rows."
                : null;

            $path = "exports/audit-log-{$export->id}.csv";
            Storage::disk('private')->put($path, $csv);

            $export->update([
                'status' => 'COMPLETED',
                'file_path' => $path,
                'total_matching' => $totalMatching,
                'exported_count' => $exportedCount,
                'truncated' => $truncated,
                'truncation_note' => $truncationNote,
            ]);

            $auditService->log(AuditAction::AUDIT_LOG_EXPORTED, $requester, null, [
                'export_id' => $export->id,
                'row_count' => $exportedCount,
                'total_matching' => $totalMatching,
                'truncated' => $truncated,
                'filters' => $filters,
            ]);
        } catch (Throwable $e) {
            OperationalAlertLogger::failure('audit_log_export', $e, ['export_id' => $this->exportId]);
            $export->update([
                'status' => 'FAILED',
                'file_path' => null,
            ]);

            throw $e;
        }
    }

    /**
     * Final safety net: if the job exhausts its retries (or dies after marking the row
     * PROCESSING), make sure the export does not stay stuck in a non-terminal state.
     */
    public function failed(Throwable $exception): void
    {
        OperationalAlertLogger::failure('audit_log_export', $exception, ['export_id' => $this->exportId]);

        $export = ReportExport::find($this->exportId);
        if ($export !== null && ! in_array($export->status, ['COMPLETED', 'FAILED'], true)) {
            $export->update([
                'status' => 'FAILED',
                'file_path' => null,
            ]);
        }
    }

    private function cell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'".$value;
        }

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}

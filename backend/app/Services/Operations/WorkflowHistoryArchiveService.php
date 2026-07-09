<?php

namespace App\Services\Operations;

use App\Enums\AuditAction;
use App\Services\Audit\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ARCH-006: workflow_history grows one row per transition with no retention
 * path. A row is only eligible once its owning engine_request is no longer
 * ACTIVE -- an in-flight request's own history rows are load-bearing for
 * EngineRequest::withStageEntry() (current-stage SLA entry lookup) and
 * ReportController::stageDuration() (consecutive-row join per request), so
 * archiving them early would silently corrupt live SLA/report data.
 */
class WorkflowHistoryArchiveService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function archiveBatch(): int
    {
        $cutoff = now()->subMonths(config('retention.workflow_history_hot_months'));
        $batchSize = config('retention.workflow_history_archive_batch_size');

        /** @var Collection<int, object> $rows */
        $rows = DB::table('workflow_history as wh')
            ->join('engine_requests as er', 'er.id', '=', 'wh.request_id')
            ->where('wh.created_at', '<', $cutoff)
            ->where('er.status', '!=', 'ACTIVE')
            ->orderBy('wh.id')
            ->limit($batchSize)
            ->select('wh.*', 'er.bank_id as request_bank_id')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $archivedAt = now();
        $archiveRows = $rows->map(function (object $row) use ($archivedAt): array {
            return [
                'source_id' => $row->id,
                'request_id' => $row->request_id,
                'bank_id' => $row->request_bank_id,
                'from_stage_id' => $row->from_stage_id,
                'to_stage_id' => $row->to_stage_id,
                'action_code' => $row->action_code,
                'performed_by' => $row->performed_by,
                'comments' => $row->comments,
                'correlation_id' => $row->correlation_id,
                'created_at' => $row->created_at,
                'archived_at' => $archivedAt,
            ];
        })->all();

        DB::table('workflow_history_archives')->insert($archiveRows);

        $ids = $rows->pluck('id')->all();
        DB::table('workflow_history')->whereIn('id', $ids)->delete();

        $this->auditService->log(
            AuditAction::WORKFLOW_HISTORY_ARCHIVED,
            null,
            null,
            [
                'archived_count' => count($ids),
                'source_ids' => $ids,
            ],
        );

        return count($ids);
    }
}

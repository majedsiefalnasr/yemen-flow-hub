<?php

namespace App\Services\Operations;

use App\Enums\AuditAction;
use App\Services\Audit\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditLogArchiveService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function archiveBatch(): int
    {
        $cutoff = now()->subMonths(config('retention.audit_hot_months'));
        $batchSize = config('retention.audit_archive_batch_size');

        /** @var Collection<int, object> $rows */
        $rows = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $archivedAt = now();
        $archiveRows = $rows->map(function (object $row) use ($archivedAt): array {
            return [
                'source_id' => $row->id,
                'user_id' => $row->user_id,
                'user_role' => $row->user_role,
                'actor_role_id' => $row->actor_role_id,
                'action' => $row->action,
                'subject_type' => $row->subject_type,
                'subject_id' => $row->subject_id,
                'workflow_instance_id' => $row->workflow_instance_id,
                'correlation_id' => $row->correlation_id,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'metadata' => $row->metadata,
                'old_values' => $row->old_values,
                'new_values' => $row->new_values,
                'created_at' => $row->created_at,
                'archived_at' => $archivedAt,
            ];
        })->all();

        DB::table('audit_log_archives')->insert($archiveRows);

        $ids = $rows->pluck('id')->all();
        DB::table('audit_logs')->whereIn('id', $ids)->delete();

        $this->auditService->log(
            AuditAction::AUDIT_ARCHIVED,
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

<?php

namespace App\Services\Notifications;

use App\Enums\StageAccessLevel;
use App\Jobs\DispatchNotification;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\DB;

class EngineNotificationDispatcher
{
    public function afterTransition(
        int $requestId,
        string $referenceNumber,
        WorkflowStage $toStage,
        string $fromStageName,
        string $toStageName,
        string $actionLabel,
    ): void {
        $userIds = $this->resolveExecuteHolders($toStage);

        $this->dispatchAfterCommit(
            type: 'transition',
            severity: 'info',
            title: "{$referenceNumber}: {$actionLabel}",
            body: "انتقل الطلب من {$fromStageName} إلى {$toStageName}",
            entityType: 'engine_request',
            entityId: $requestId,
            actionUrl: "/requests/{$requestId}",
            recipientUserIds: $userIds,
        );
    }

    public function afterApproveRejectReturn(
        int $requestId,
        string $referenceNumber,
        string $decision,
        ?string $reason,
        array $recipientUserIds,
    ): void {
        $severityMap = [
            'approved' => 'success',
            'rejected' => 'critical',
            'returned' => 'warning',
        ];

        $labelMap = [
            'approved' => 'تمت الموافقة',
            'rejected' => 'تم الرفض',
            'returned' => 'تمت الإعادة',
        ];

        $label = $labelMap[$decision] ?? $decision;

        $this->dispatchAfterCommit(
            type: "decision.{$decision}",
            severity: $severityMap[$decision] ?? 'info',
            title: "{$referenceNumber}: {$label}",
            body: $reason,
            entityType: 'engine_request',
            entityId: $requestId,
            actionUrl: "/requests/{$requestId}",
            recipientUserIds: $recipientUserIds,
        );
    }

    public function afterWorkflowPublished(
        int $definitionId,
        string $workflowName,
        string $versionLabel,
        array $recipientUserIds,
    ): void {
        $this->dispatchAfterCommit(
            type: 'workflow.published',
            severity: 'info',
            title: "نُشر سير العمل: {$workflowName} ({$versionLabel})",
            body: null,
            entityType: 'workflow_definition',
            entityId: $definitionId,
            actionUrl: null,
            recipientUserIds: $recipientUserIds,
        );
    }

    public function afterPermissionChange(
        int $roleId,
        string $roleName,
        int $actorId,
    ): void {
        $recipientIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId))
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        $this->dispatchAfterCommit(
            type: 'permission.changed',
            severity: 'warning',
            title: "تم تحديث صلاحيات الدور: {$roleName}",
            body: null,
            entityType: 'role',
            entityId: $roleId,
            actionUrl: null,
            recipientUserIds: $recipientIds,
        );
    }

    public function custom(
        string $type,
        string $severity,
        string $title,
        ?string $body,
        ?string $entityType,
        ?int $entityId,
        ?string $actionUrl,
        array $recipientUserIds,
    ): void {
        $this->dispatchAfterCommit($type, $severity, $title, $body, $entityType, $entityId, $actionUrl, $recipientUserIds);
    }

    /**
     * Resolve users that hold EXECUTE on a given stage.
     *
     * @return int[]
     */
    private function resolveExecuteHolders(WorkflowStage $stage): array
    {
        $rows = StagePermission::query()
            ->where('stage_id', $stage->getKey())
            ->where('access_level', StageAccessLevel::EXECUTE->value)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $query = User::query()->where('is_active', true);

        $query->where(function ($q) use ($rows) {
            foreach ($rows as $row) {
                $q->orWhere(function ($sub) use ($row) {
                    if ($row->organization_id !== null) {
                        $sub->where('organization_id', $row->organization_id);
                    }
                    if ($row->role_id !== null) {
                        $sub->whereHas('roles', fn ($rq) => $rq->where('roles.id', $row->role_id));
                    }
                    if ($row->team_id !== null) {
                        $sub->whereHas('teams', fn ($tq) => $tq->where('teams.id', $row->team_id));
                    }
                    if ($row->user_id !== null) {
                        $sub->where('users.id', $row->user_id);
                    }
                });
            }
        });

        return $query->pluck('id')->toArray();
    }

    private function dispatchAfterCommit(
        string $type,
        string $severity,
        string $title,
        ?string $body,
        ?string $entityType,
        ?int $entityId,
        ?string $actionUrl,
        array $recipientUserIds,
    ): void {
        if (empty($recipientUserIds)) {
            return;
        }

        DB::afterCommit(function () use ($type, $severity, $title, $body, $entityType, $entityId, $actionUrl, $recipientUserIds) {
            DispatchNotification::dispatch(
                $type, $severity, $title, $body,
                $entityType, $entityId, $actionUrl,
                $recipientUserIds
            );
        });
    }
}

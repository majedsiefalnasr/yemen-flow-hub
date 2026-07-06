<?php

namespace App\Services\Notifications;

use App\Jobs\DispatchNotification;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\Workflow\StagePermissionAudience;
use Illuminate\Support\Facades\DB;

class EngineNotificationDispatcher
{
    public function __construct(
        private StagePermissionAudience $stagePermissionAudience,
    ) {}

    public function afterTransition(
        int $requestId,
        string $referenceNumber,
        WorkflowStage $toStage,
        string $fromStageName,
        string $toStageName,
        string $actionLabel,
    ): void {
        $userIds = $this->stagePermissionAudience->executeHolderIds($toStage);

        $this->dispatchAfterCommit(
            type: 'transition',
            severity: 'info',
            title: "{$referenceNumber}: {$actionLabel}",
            body: "انتقل الطلب من {$fromStageName} إلى {$toStageName}",
            entityType: 'engine_request',
            entityId: $requestId,
            actionUrl: $this->engineRequestActionUrl($requestId),
            recipientUserIds: $userIds,
        );
    }

    /**
     * Notify the oversight audience when a submitted request shares an invoice number
     * with an existing active request (compliance signal, FR-NOTIF2).
     *
     * @param  array<int, array{id: int, reference: string}>  $duplicates
     */
    public function afterDuplicateInvoice(
        int $requestId,
        string $referenceNumber,
        string $invoiceNumber,
        array $duplicates,
    ): void {
        $refs = implode('، ', array_column($duplicates, 'reference'));

        $this->dispatchAfterCommit(
            type: 'compliance.duplicate_invoice',
            severity: 'warning',
            title: "تكرار رقم فاتورة: {$referenceNumber}",
            body: "رقم الفاتورة {$invoiceNumber} مطابق لطلبات قائمة: {$refs}",
            entityType: 'engine_request',
            entityId: $requestId,
            actionUrl: $this->engineRequestActionUrl($requestId),
            recipientUserIds: $this->resolveAuditViewers(),
        );
    }

    /**
     * Notify the oversight audience that a request has breached or is nearing its
     * stage SLA. Dispatched by the scheduled SLA scan (FR-NOTIF2).
     */
    public function afterSlaSignal(
        int $requestId,
        string $referenceNumber,
        string $slaStatus,
        string $stageName,
    ): void {
        $isBreached = $slaStatus === 'breached';

        $this->dispatchAfterCommit(
            type: $isBreached ? 'sla.breached' : 'sla.nearing',
            severity: $isBreached ? 'critical' : 'warning',
            title: $isBreached
                ? "تجاوز المهلة: {$referenceNumber}"
                : "اقتراب المهلة: {$referenceNumber}",
            body: "المرحلة: {$stageName}",
            entityType: 'engine_request',
            entityId: $requestId,
            actionUrl: $this->engineRequestActionUrl($requestId),
            recipientUserIds: $this->resolveAuditViewers(),
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

    public function engineRequestActionUrl(int $requestId): string
    {
        return "/workflows/instances/{$requestId}";
    }

    /**
     * Resolve active users in any role that holds VIEW on the `audit` screen — the
     * oversight audience for compliance and SLA signals (data-driven, not role codes).
     *
     * @return int[]
     */
    private function resolveAuditViewers(): array
    {
        $roleIds = DB::table('screen_permissions')
            ->join('screens', 'screens.id', '=', 'screen_permissions.screen_id')
            ->where('screens.key', 'audit')
            ->where('screen_permissions.capability', 'VIEW')
            ->pluck('screen_permissions.role_id')
            ->all();

        if (empty($roleIds)) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->pluck('id')
            ->toArray();
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

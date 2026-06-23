<?php

namespace App\Services\Workflow;

use App\Enums\AuditAction;
use App\Exceptions\StaleResourceException;
use App\Exceptions\WorkflowDesignProtectionException;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Global, version-independent catalog of reusable workflow actions. Actions are
 * referenced by per-version transitions (18.4.4). `code` is immutable; `name` and
 * `is_active` are editable. System defaults and in-use actions are protected.
 */
class WorkflowActionService
{
    public function __construct(private readonly AuditService $auditService) {}

    public function create(User $actor, array $attributes): WorkflowAction
    {
        return DB::transaction(function () use ($actor, $attributes): WorkflowAction {
            $action = WorkflowAction::query()->create($attributes)->refresh();
            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $action,
                ['after' => $action->toArray()],
            );

            return $action;
        });
    }

    public function update(
        User $actor,
        WorkflowAction $action,
        array $attributes,
        int $expectedVersion,
    ): WorkflowAction {
        return DB::transaction(function () use ($actor, $action, $attributes, $expectedVersion): WorkflowAction {
            $locked = WorkflowAction::query()->lockForUpdate()->findOrFail($action->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);

            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }

    public function setActive(
        User $actor,
        WorkflowAction $action,
        bool $active,
        int $expectedVersion,
    ): WorkflowAction {
        // Deactivating an in-use action would silently break transitions; block it.
        if (! $active && $this->isInUse($action)) {
            $this->auditBlocked($actor, $action, 'workflow_action_in_use_deactivate');
            throw new WorkflowDesignProtectionException(
                'WORKFLOW_ACTION_IN_USE',
                'Action cannot be deactivated while it is used by a transition.',
            );
        }

        return DB::transaction(function () use ($actor, $action, $active, $expectedVersion): WorkflowAction {
            $locked = WorkflowAction::query()->lockForUpdate()->findOrFail($action->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);

            if ($locked->is_active === $active) {
                return $locked;
            }

            $before = $locked->only(['is_active', 'version']);
            $locked->update([
                'is_active' => $active,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->only(['is_active', 'version'])],
            );

            return $locked->refresh();
        });
    }

    public function delete(User $actor, WorkflowAction $action): void
    {
        $blockReason = DB::transaction(function () use ($actor, $action): ?string {
            $locked = WorkflowAction::query()->lockForUpdate()->findOrFail($action->getKey());

            if ($locked->isProtected()) {
                return 'workflow_action_system_protected';
            }
            if ($this->isInUse($locked)) {
                return 'workflow_action_in_use';
            }

            $before = $locked->toArray();
            $locked->delete();
            $this->auditService->log(
                AuditAction::GOVERNANCE_DELETED,
                $actor,
                $locked,
                ['before' => $before],
            );

            return null;
        });

        if ($blockReason !== null) {
            $this->auditBlocked($actor, $action, $blockReason);
            throw new WorkflowDesignProtectionException(
                'WORKFLOW_ACTION_PROTECTED',
                'Action cannot be deleted because it is a system default or is used by a transition.',
            );
        }
    }

    /**
     * Whether the action is referenced by any transition. `workflow_transitions`
     * arrives in 18.4.4; until then nothing references an action.
     */
    private function isInUse(WorkflowAction $action): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('workflow_transitions')) {
            return false;
        }

        return DB::table('workflow_transitions')->where('action_id', $action->getKey())->exists();
    }

    private function ensureCurrentVersion(int $actualVersion, int $expectedVersion): void
    {
        if ($actualVersion !== $expectedVersion) {
            throw new StaleResourceException;
        }
    }

    private function auditBlocked(User $actor, WorkflowAction $action, string $reason): void
    {
        $this->auditService->log(
            AuditAction::AUTHORIZATION_FAILURE,
            $actor,
            $action,
            ['reason' => $reason],
        );
    }
}

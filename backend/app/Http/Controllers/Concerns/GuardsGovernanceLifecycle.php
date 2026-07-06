<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AuditAction;
use App\Enums\GovernanceReferenceEntityType;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Workflow\PublishedWorkflowReferenceGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

trait GuardsGovernanceLifecycle
{
    abstract protected function auditService(): AuditService;

    abstract protected function workflowReferenceGuard(): PublishedWorkflowReferenceGuard;

    protected function assertCanDeleteGovernanceEntity(
        GovernanceReferenceEntityType $entityType,
        Model $entity,
        User $actor,
        callable $structuralBlock,
    ): ?JsonResponse {
        $structural = $structuralBlock();
        if ($structural instanceof JsonResponse) {
            $this->auditBlockedGovernanceMutation($actor, $entity, 'structural_guard');

            return $structural;
        }

        if ($this->workflowReferenceGuard()->isReferencedByPublishedPermissions($entityType, (int) $entity->getKey())) {
            $this->auditBlockedGovernanceMutation($actor, $entity, 'referenced_by_published_workflow');

            return $this->governanceError(
                $entityType->deleteBlockedErrorCode(),
                'Entity cannot be deleted while referenced by a published workflow.',
                422,
            );
        }

        return null;
    }

    protected function assertCanDeactivateGovernanceEntity(
        GovernanceReferenceEntityType $entityType,
        Model $entity,
        User $actor,
        callable $structuralBlock,
    ): ?JsonResponse {
        $structural = $structuralBlock();
        if ($structural instanceof JsonResponse) {
            $this->auditBlockedGovernanceMutation($actor, $entity, 'structural_guard');

            return $structural;
        }

        if ($this->workflowReferenceGuard()->wouldLeaveStageWithoutExecutor($entityType, (int) $entity->getKey())) {
            $this->auditBlockedGovernanceMutation($actor, $entity, 'would_break_executor');

            return $this->governanceError(
                $entityType->deactivateBlockedErrorCode(),
                'Deactivation would leave a published stage without an effective executor.',
                422,
            );
        }

        return null;
    }

    protected function auditGovernanceDelete(User $actor, Model $entity, array $snapshot): void
    {
        $this->auditService()->log(AuditAction::GOVERNANCE_DELETED, $actor, $entity, [
            'before' => $snapshot,
        ]);
    }

    protected function auditBlockedGovernanceMutation(User $actor, Model $entity, string $reason): void
    {
        $this->auditService()->log(AuditAction::AUTHORIZATION_FAILURE, $actor, $entity, [
            'reason' => $reason,
        ]);
    }

    protected function governanceError(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'fields' => (object) $fields,
                'request_id' => request()->header('X-Request-ID'),
            ],
        ], $status);
    }
}

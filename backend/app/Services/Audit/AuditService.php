<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        AuditAction $action,
        ?User $actor,
        ?Model $subject = null,
        array $metadata = [],
        ?string $subjectTypeOverride = null,
        ?int $workflowInstanceId = null,
        ?string $correlationId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $request = $this->currentRequest();
        $rawUa = $request?->userAgent();

        $engineRole = $actor?->role();
        $actorRoleId = $engineRole?->id;
        $userRoleString = $actor?->asUserRole()?->value ?? $engineRole?->code;

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'user_role' => $userRoleString,
            'actor_role_id' => $actorRoleId,
            'action' => $action->value,
            'subject_type' => $subjectTypeOverride ?? ($subject ? $subject::class : null),
            'subject_id' => $subject?->getKey(),
            'workflow_instance_id' => $workflowInstanceId,
            'correlation_id' => $correlationId,
            'ip_address' => $request?->ip(),
            'user_agent' => $rawUa !== null ? mb_substr($rawUa, 0, 512) : null,
            'metadata' => $metadata,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);
    }

    private function currentRequest(): ?Request
    {
        $request = app('request');

        if (! $request instanceof Request) {
            return null;
        }

        if (app()->runningInConsole() && $request->route() === null) {
            return null;
        }

        return $request;
    }
}

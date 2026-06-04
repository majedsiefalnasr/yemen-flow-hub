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
        ?string $subjectTypeOverride = null
    ): AuditLog {
        $request = $this->currentRequest();
        $rawUa = $request?->userAgent();

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'user_role' => $actor?->role?->value,
            'action' => $action->value,
            'subject_type' => $subjectTypeOverride ?? ($subject ? $subject::class : null),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $rawUa !== null ? mb_substr($rawUa, 0, 512) : null,
            'metadata' => $metadata,
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

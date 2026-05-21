<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function log(
        AuditAction $action,
        ?User $actor,
        ?Model $subject = null,
        array $metadata = [],
        ?string $subjectTypeOverride = null
    ): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'user_role' => $actor?->role?->value,
            'action' => $action->value,
            'subject_type' => $subjectTypeOverride ?? ($subject ? $subject::class : null),
            'subject_id' => $subject?->getKey(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

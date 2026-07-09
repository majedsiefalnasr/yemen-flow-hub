<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * Audit logs are append-only. Block any app-layer update or delete so the trail
     * cannot be altered or erased after a row is written (FR-AUD1).
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Audit logs are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Audit logs are append-only and cannot be deleted.');
        });
    }

    protected $fillable = [
        'user_id',
        'user_role',
        'actor_role_id',
        'action',
        'subject_type',
        'subject_id',
        'bank_id',
        'workflow_instance_id',
        'correlation_id',
        'ip_address',
        'user_agent',
        'metadata',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actorRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'actor_role_id');
    }

    public function engineRequest(): BelongsTo
    {
        return $this->belongsTo(EngineRequest::class, 'workflow_instance_id');
    }
}

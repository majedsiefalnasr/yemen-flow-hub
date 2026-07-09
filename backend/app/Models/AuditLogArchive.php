<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLogArchive extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source_id',
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
        'created_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'archived_at' => 'datetime',
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

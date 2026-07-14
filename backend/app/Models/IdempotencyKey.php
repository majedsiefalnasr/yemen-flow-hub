<?php

namespace App\Models;

use App\Enums\IdempotencyKeyState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key',
        'user_id',
        'organization_id',
        'operation',
        'request_fingerprint',
        'state',
        'claim_token',
        'locked_until',
        'response_status',
        'response_body',
        'engine_request_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => IdempotencyKeyState::class,
            'locked_until' => 'datetime',
            'response_status' => 'integer',
            'response_body' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function engineRequest(): BelongsTo
    {
        return $this->belongsTo(EngineRequest::class, 'engine_request_id');
    }
}

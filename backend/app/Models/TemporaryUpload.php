<?php

namespace App\Models;

use App\Enums\DocumentScanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryUpload extends Model
{
    protected $table = 'temporary_uploads';

    protected $fillable = [
        'token',
        'upload_session_token',
        'user_id',
        'organization_id',
        'bank_id',
        'workflow_version_id',
        'field_id',
        'original_name',
        'path',
        'mime',
        'size',
        'checksum',
        'scan_status',
        'expires_at',
        'consumed_at',
        'reserved_by_idempotency_key_id',
        'reservation_claim_token',
        'reservation_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'scan_status' => DocumentScanStatus::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'reservation_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class, 'field_id');
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function reservedByIdempotencyKey(): BelongsTo
    {
        return $this->belongsTo(IdempotencyKey::class, 'reserved_by_idempotency_key_id');
    }

    public function isReservedBy(int $idempotencyKeyId, string $claimToken): bool
    {
        return $this->reserved_by_idempotency_key_id === $idempotencyKeyId
            && $this->reservation_claim_token === $claimToken;
    }
}

<?php

namespace App\Models;

use App\Enums\DocumentScanStatus;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EngineRequestDocument extends Model
{
    use SoftDeletes;

    protected $table = 'engine_request_documents';

    protected $fillable = [
        'request_id',
        'field_id',
        'uploaded_by',
        'stage_id',
        'original_name',
        'path',
        'mime',
        'size',
        'checksum',
        'scan_status',
        'version',
        'status',
        'superseded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
            'scan_status' => DocumentScanStatus::class,
            'status' => DocumentStatus::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(EngineRequest::class, 'request_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class, 'field_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    public function supersededByDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by');
    }

    public function isActive(): bool
    {
        return ($this->status ?? DocumentStatus::Active)->isActive();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Active->value);
    }
}

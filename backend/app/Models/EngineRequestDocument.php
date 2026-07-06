<?php

namespace App\Models;

use App\Enums\DocumentScanStatus;
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
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
            'scan_status' => DocumentScanStatus::class,
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
}

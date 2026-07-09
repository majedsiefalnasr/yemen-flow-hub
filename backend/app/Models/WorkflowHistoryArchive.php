<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowHistoryArchive extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source_id',
        'request_id',
        'bank_id',
        'from_stage_id',
        'to_stage_id',
        'action_code',
        'performed_by',
        'comments',
        'correlation_id',
        'created_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'to_stage_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

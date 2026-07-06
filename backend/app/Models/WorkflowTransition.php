<?php

namespace App\Models;

use App\Enums\WorkflowTransitionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'from_stage_id',
        'action_id',
        'to_stage_id',
        'requires_comment',
        'confirmation_message',
        'is_default_submit',
        'is_self_loop',
        'transition_type',
        'is_destructive',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'requires_comment' => 'boolean',
            'is_default_submit' => 'boolean',
            'is_self_loop' => 'boolean',
            'transition_type' => WorkflowTransitionType::class,
            'is_destructive' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'to_stage_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(WorkflowAction::class, 'action_id');
    }
}

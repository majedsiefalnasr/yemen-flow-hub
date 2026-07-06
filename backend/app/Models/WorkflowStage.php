<?php

namespace App\Models;

use App\Enums\FinalOutcome;
use App\Enums\StageSemanticRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'code',
        'semantic_role',
        'attached_effects',
        'name',
        'description',
        'sort_order',
        'is_initial',
        'is_final',
        'final_outcome',
        'sla_duration_minutes',
        'requires_claim',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'semantic_role' => StageSemanticRole::class,
            'attached_effects' => 'array',
            'is_initial' => 'boolean',
            'is_final' => 'boolean',
            'final_outcome' => FinalOutcome::class,
            'sla_duration_minutes' => 'integer',
            'requires_claim' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function stagePermissions(): HasMany
    {
        return $this->hasMany(StagePermission::class, 'stage_id');
    }

    public function stageFieldRules(): HasMany
    {
        return $this->hasMany(StageFieldRule::class, 'stage_id');
    }
}

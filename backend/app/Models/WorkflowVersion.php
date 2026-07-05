<?php

namespace App\Models;

use App\Enums\WorkflowVersionState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class WorkflowVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_definition_id',
        'version_number',
        'state',
        'published_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'state' => WorkflowVersionState::class,
            'version_number' => 'integer',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class, 'workflow_version_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'workflow_version_id');
    }

    public function fieldGroups(): HasMany
    {
        return $this->hasMany(FieldGroup::class, 'workflow_version_id');
    }

    public function fieldDefinitions(): HasMany
    {
        return $this->hasMany(FieldDefinition::class, 'workflow_version_id');
    }

    public function fields(): HasManyThrough
    {
        return $this->hasManyThrough(
            FieldDefinition::class,
            FieldGroup::class,
            'workflow_version_id',
            'field_group_id',
        );
    }

    public function isEditable(): bool
    {
        return $this->state->isEditable();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldGroup extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_version_id', 'name', 'label', 'sort_order', 'version'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'version' => 'integer',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FieldDefinition::class, 'field_group_id');
    }
}

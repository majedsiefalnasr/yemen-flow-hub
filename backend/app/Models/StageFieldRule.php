<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageFieldRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'field_id',
        'is_visible',
        'is_editable',
        'is_required',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_editable' => 'boolean',
            'is_required' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class, 'field_id');
    }
}

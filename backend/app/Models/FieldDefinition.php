<?php

namespace App\Models;

use App\Enums\DynamicFieldSource;
use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'field_group_id',
        'key',
        'label',
        'type',
        'placeholder',
        'help_text',
        'default_value',
        'min_value',
        'max_value',
        'min_length',
        'max_length',
        'regex_pattern',
        'options',
        'reference_table_id',
        'dynamic_source',
        'allowed_file_types',
        'max_file_size',
        'multiple',
        'is_required',
        'is_system',
        'sort_order',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'dynamic_source' => DynamicFieldSource::class,
            'options' => 'array',
            'allowed_file_types' => 'array',
            'min_value' => 'decimal:4',
            'max_value' => 'decimal:4',
            'min_length' => 'integer',
            'max_length' => 'integer',
            'max_file_size' => 'integer',
            'multiple' => 'boolean',
            'is_required' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
            'version' => 'integer',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function fieldGroup(): BelongsTo
    {
        return $this->belongsTo(FieldGroup::class, 'field_group_id');
    }

    public function referenceTable(): BelongsTo
    {
        return $this->belongsTo(ReferenceTable::class, 'reference_table_id');
    }

    public function isProtected(): bool
    {
        return (bool) $this->is_system;
    }
}

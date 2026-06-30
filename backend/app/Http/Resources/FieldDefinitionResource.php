<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_version_id' => $this->workflow_version_id,
            'field_group_id' => $this->field_group_id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'placeholder' => $this->placeholder,
            'help_text' => $this->help_text,
            'default_value' => $this->default_value,
            'min_value' => $this->min_value !== null ? (float) $this->min_value : null,
            'max_value' => $this->max_value !== null ? (float) $this->max_value : null,
            'min_length' => $this->min_length,
            'max_length' => $this->max_length,
            'regex_pattern' => $this->regex_pattern,
            'options' => $this->options,
            'reference_table_id' => $this->reference_table_id,
            'dynamic_source' => $this->dynamic_source?->value,
            'allowed_file_types' => $this->allowed_file_types,
            'max_file_size' => $this->max_file_size,
            'multiple' => (bool) $this->multiple,
            'is_required' => (bool) $this->is_required,
            'is_system' => (bool) $this->is_system,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => (int) $this->version,
        ];
    }
}

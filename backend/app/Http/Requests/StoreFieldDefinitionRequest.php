<?php

namespace App\Http\Requests;

use App\Enums\DynamicFieldSource;
use App\Enums\FieldSemanticTag;
use App\Enums\FieldType;
use App\Models\WorkflowVersion;
use App\Support\FieldDefinitionConstraintValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $version = $this->route('workflowVersion');
        $versionId = $version instanceof WorkflowVersion ? $version->getKey() : null;

        return [
            'field_group_id' => [
                'required', 'integer',
                Rule::exists('field_groups', 'id')->where('workflow_version_id', $versionId),
            ],
            'key' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('field_definitions', 'key')->where('workflow_version_id', $versionId),
            ],
            'semantic_tag' => ['sometimes', 'nullable', Rule::enum(FieldSemanticTag::class)],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(FieldType::class)],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'help_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'default_value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'min_value' => ['sometimes', 'nullable', 'numeric'],
            'max_value' => ['sometimes', 'nullable', 'numeric'],
            'min_length' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_length' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'regex_pattern' => ['sometimes', 'nullable', 'string', 'max:255'],
            'options' => ['sometimes', 'nullable', 'array'],
            'reference_table_id' => ['sometimes', 'nullable', 'integer', 'exists:reference_tables,id'],
            'dynamic_source' => ['sometimes', 'nullable', Rule::enum(DynamicFieldSource::class)],
            'allowed_file_types' => ['sometimes', 'nullable', 'array'],
            'max_file_size' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'multiple' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') !== FieldType::DYNAMIC_SELECT->value) {
                    $this->validateFieldConstraints($validator);

                    return;
                }

                $source = $this->input('dynamic_source');
                if ($source === null) {
                    $validator->errors()->add('dynamic_source', 'A DYNAMIC_SELECT field requires a dynamic_source.');

                    return;
                }

                if (
                    $source === DynamicFieldSource::REFERENCE_DATA->value
                    && $this->input('reference_table_id') === null
                ) {
                    $validator->errors()->add(
                        'reference_table_id',
                        'A REFERENCE_DATA dynamic source requires a reference_table_id.',
                    );
                }

                $this->validateFieldConstraints($validator);
            },
        ];
    }

    private function validateFieldConstraints(Validator $validator): void
    {
        foreach (FieldDefinitionConstraintValidator::validate($this->all()) as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }
}

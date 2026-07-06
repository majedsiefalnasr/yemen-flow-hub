<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Enums\FieldSemanticTag;
use App\Models\FieldDefinition;
use App\Services\Audit\AuditService;
use App\Support\FieldDefinitionConstraintValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // key and type are immutable once defined — change via a new version.
        return [
            'key' => ['sometimes', 'string'],
            'semantic_tag' => ['sometimes', 'nullable', Rule::enum(FieldSemanticTag::class)],
            'label' => ['sometimes', 'string', 'max:255'],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'help_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'default_value' => ['sometimes', 'nullable', 'string', 'max:255'],
            'min_value' => ['sometimes', 'nullable', 'numeric'],
            'max_value' => ['sometimes', 'nullable', 'numeric'],
            'min_length' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_length' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'regex_pattern' => ['sometimes', 'nullable', 'string', 'max:255'],
            'options' => ['sometimes', 'nullable', 'array'],
            'allowed_file_types' => ['sometimes', 'nullable', 'array'],
            'max_file_size' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'multiple' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $field = $this->route('fieldDefinition');
                if (! $field instanceof FieldDefinition) {
                    return;
                }
                if (! $this->has('key') || $this->input('key') === $field->key) {
                    return;
                }

                app(AuditService::class)->log(
                    AuditAction::AUTHORIZATION_FAILURE,
                    $this->user(),
                    $field,
                    ['reason' => 'field_definition_key_change_attempt', 'attempted_key' => $this->input('key')],
                );
                $validator->errors()->add('key', 'The field key is immutable. Change it in a new version.');
            },
            function (Validator $validator): void {
                $field = $this->route('fieldDefinition');
                if (! $field instanceof FieldDefinition) {
                    return;
                }

                $payload = array_merge($field->toArray(), $this->all());
                foreach (FieldDefinitionConstraintValidator::validate($payload) as $fieldKey => $message) {
                    $validator->errors()->add($fieldKey, $message);
                }
            },
        ];
    }
}

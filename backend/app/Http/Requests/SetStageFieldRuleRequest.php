<?php

namespace App\Http\Requests;

use App\Models\WorkflowStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetStageFieldRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $stage = $this->route('workflowStage');
        $versionId = $stage instanceof WorkflowStage ? $stage->workflow_version_id : null;

        return [
            'field_id' => [
                'required', 'integer',
                Rule::exists('field_definitions', 'id')->where('workflow_version_id', $versionId),
            ],
            'is_visible' => ['sometimes', 'boolean'],
            'is_editable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
        ];
    }
}

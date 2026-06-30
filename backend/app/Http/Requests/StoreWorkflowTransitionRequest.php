<?php

namespace App\Http\Requests;

use App\Models\WorkflowVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowTransitionRequest extends FormRequest
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
            'from_stage_id' => [
                'required', 'integer',
                Rule::exists('workflow_stages', 'id')->where('workflow_version_id', $versionId),
                Rule::unique('workflow_transitions', 'from_stage_id')
                    ->where('action_id', $this->input('action_id')),
            ],
            'to_stage_id' => [
                'required', 'integer',
                Rule::exists('workflow_stages', 'id')->where('workflow_version_id', $versionId),
            ],
            'action_id' => [
                'required', 'integer',
                Rule::exists('workflow_actions', 'id')->where('is_active', true),
            ],
            'requires_comment' => ['sometimes', 'boolean'],
            'confirmation_message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}

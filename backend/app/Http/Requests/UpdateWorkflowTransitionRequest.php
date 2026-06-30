<?php

namespace App\Http\Requests;

use App\Models\WorkflowTransition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // from_stage_id / action_id form the unique pair and are not editable here —
        // delete and recreate to re-wire. Only behavioural fields are editable.
        $transition = $this->route('workflowTransition');
        $versionId = $transition instanceof WorkflowTransition ? $transition->workflow_version_id : null;

        return [
            'to_stage_id' => [
                'sometimes', 'integer',
                Rule::exists('workflow_stages', 'id')->where('workflow_version_id', $versionId),
            ],
            'requires_comment' => ['sometimes', 'boolean'],
            'confirmation_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

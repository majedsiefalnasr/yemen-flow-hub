<?php

namespace App\Http\Requests;

use App\Models\WorkflowStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $stage = $this->route('workflowStage');
        $versionId = $stage instanceof WorkflowStage ? $stage->workflow_version_id : null;
        $stageId = $stage instanceof WorkflowStage ? $stage->getKey() : null;

        return [
            'code' => [
                'sometimes', 'string', 'max:100', 'alpha_dash',
                Rule::unique('workflow_stages', 'code')
                    ->where('workflow_version_id', $versionId)
                    ->ignore($stageId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_initial' => ['sometimes', 'boolean'],
            'is_final' => ['sometimes', 'boolean'],
            'sla_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:ACTIVE,INACTIVE'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

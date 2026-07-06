<?php

namespace App\Http\Requests;

use App\Enums\WorkflowTransitionType;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'is_default_submit' => ['sometimes', 'boolean'],
            'is_self_loop' => ['sometimes', 'boolean'],
            'transition_type' => ['sometimes', Rule::enum(WorkflowTransitionType::class)],
            'is_destructive' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_default_submit')) {
                    return;
                }

                $fromStageId = $this->integer('from_stage_id');
                $fromStage = WorkflowStage::query()->find($fromStageId);
                if ($fromStage !== null && ! $fromStage->is_initial) {
                    $validator->errors()->add(
                        'is_default_submit',
                        'Only transitions from the initial stage may be marked as the default submit transition.',
                    );
                }
            },
        ];
    }
}

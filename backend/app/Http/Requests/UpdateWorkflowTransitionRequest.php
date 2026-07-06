<?php

namespace App\Http\Requests;

use App\Enums\WorkflowTransitionType;
use App\Models\WorkflowTransition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkflowTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $transition = $this->route('workflowTransition');
        $versionId = $transition instanceof WorkflowTransition ? $transition->workflow_version_id : null;

        return [
            'to_stage_id' => [
                'sometimes', 'integer',
                Rule::exists('workflow_stages', 'id')->where('workflow_version_id', $versionId),
            ],
            'requires_comment' => ['sometimes', 'boolean'],
            'confirmation_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_default_submit' => ['sometimes', 'boolean'],
            'is_self_loop' => ['sometimes', 'boolean'],
            'transition_type' => ['sometimes', Rule::enum(WorkflowTransitionType::class)],
            'is_destructive' => ['sometimes', 'boolean'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_default_submit')) {
                    return;
                }

                /** @var WorkflowTransition|null $transition */
                $transition = $this->route('workflowTransition');
                if ($transition === null) {
                    return;
                }

                $fromStage = $transition->fromStage;
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

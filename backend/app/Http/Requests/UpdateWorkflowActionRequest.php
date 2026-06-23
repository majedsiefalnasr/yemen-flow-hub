<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Enums\WorkflowActionKind;
use App\Models\WorkflowAction;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkflowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::enum(WorkflowActionKind::class)],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $action = $this->route('workflowAction');
                if (! $action instanceof WorkflowAction) {
                    return;
                }
                if (! $this->has('code') || $this->input('code') === $action->code) {
                    return;
                }

                app(AuditService::class)->log(
                    AuditAction::AUTHORIZATION_FAILURE,
                    $this->user(),
                    $action,
                    ['reason' => 'workflow_action_code_change_attempt', 'attempted_code' => $this->input('code')],
                );
                $validator->errors()->add('code', 'The workflow action code is immutable.');
            },
        ];
    }
}

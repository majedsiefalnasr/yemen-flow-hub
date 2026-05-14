<?php

namespace App\Http\Requests;

class WorkflowActionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getName();
        $requiresReason = in_array($action, [
            'workflow.bank-reject',
            'workflow.return-to-entry',
            'workflow.support-reject',
        ], true);

        return [
            'reason' => [$requiresReason ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }
}

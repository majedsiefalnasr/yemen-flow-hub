<?php

namespace App\Http\Requests;

use App\Enums\WorkflowActionKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:workflow_actions,code'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(WorkflowActionKind::class)],
        ];
    }
}

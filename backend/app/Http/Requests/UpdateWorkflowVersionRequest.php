<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Engine-core version has no editable scalar fields yet; sibling stories
            // (stages/actions/transitions/fields) add the editable payload. The
            // version guard (optimistic lock) and DRAFT-only enforcement apply now.
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

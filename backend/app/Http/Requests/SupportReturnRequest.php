<?php

namespace App\Http\Requests;

class SupportReturnRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'comment.required',
        ];
    }
}

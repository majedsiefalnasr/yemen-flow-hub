<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', Rule::prohibitedIf(fn () => $this->input('code') !== $this->route('organization')->code)],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

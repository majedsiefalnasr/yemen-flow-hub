<?php

namespace App\Http\Requests;

use App\Enums\OrganizationClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
            'classification' => ['sometimes', new Enum(OrganizationClassification::class)],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

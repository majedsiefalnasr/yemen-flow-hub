<?php

namespace App\Http\Requests;

use App\Enums\OrganizationClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:organizations,code'],
            'name' => ['required', 'string', 'max:255'],
            'classification' => ['required', new Enum(OrganizationClassification::class)],
        ];
    }
}

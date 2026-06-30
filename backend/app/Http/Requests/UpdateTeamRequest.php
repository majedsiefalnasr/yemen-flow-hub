<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'organization_id' => ['sometimes', Rule::prohibitedIf(fn () => $this->integer('organization_id') !== $team->organization_id)],
            'code' => ['sometimes', Rule::prohibitedIf(fn () => $this->input('code') !== $team->code)],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
            'role_code' => ['prohibited'],
        ];
    }
}

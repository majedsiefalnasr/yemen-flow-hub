<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBankRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankId = $this->route('bank')?->id;

        return [
            'name_ar' => ['required', 'string', 'max:255', Rule::unique('banks', 'name_ar')->ignore($bankId)],
            'name_en' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('banks', 'code')->ignore($bankId)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

class StoreBankRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255', 'unique:banks,name_ar'],
            'name_en' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:banks,code'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

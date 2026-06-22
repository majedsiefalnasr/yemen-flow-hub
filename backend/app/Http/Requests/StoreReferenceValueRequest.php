<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferenceValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_table_id' => ['required', 'integer', 'exists:reference_tables,id'],
            'key' => [
                'required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('reference_values')->where('reference_table_id', $this->integer('reference_table_id')),
            ],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

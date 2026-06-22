<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferenceValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $referenceValue = $this->route('reference_value');

        return [
            'reference_table_id' => ['sometimes', Rule::prohibitedIf(fn () => $this->integer('reference_table_id') !== $referenceValue->reference_table_id)],
            'key' => ['sometimes', Rule::prohibitedIf(fn () => $this->input('key') !== $referenceValue->key)],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

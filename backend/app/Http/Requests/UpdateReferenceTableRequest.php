<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferenceTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $referenceTable = $this->route('reference_table');

        return [
            'key' => ['sometimes', Rule::prohibitedIf(fn () => $this->input('key') !== $referenceTable->key)],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

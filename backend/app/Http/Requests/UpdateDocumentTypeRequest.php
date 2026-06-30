<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateDocumentTypeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('documentType')?->id;

        return [
            'slug' => ['required', 'string', 'max:255', Rule::unique('document_types', 'slug')->ignore($id)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

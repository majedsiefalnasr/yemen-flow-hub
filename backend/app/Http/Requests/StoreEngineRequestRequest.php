<?php

namespace App\Http\Requests;

class StoreEngineRequestRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'workflow_version_id' => ['required', 'integer', 'exists:workflow_versions,id'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'merchant_id' => ['nullable', 'integer', 'exists:merchants,id'],
            // A brand-new draft is created empty, then filled step by step in the
            // wizard, so an empty object is valid. Use `present` (key must exist,
            // may be []) instead of `required` (which rejects an empty array).
            'data' => ['present', 'array'],
            'data.*' => ['nullable'],
        ];
    }
}

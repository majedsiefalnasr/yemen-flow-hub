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
            'data' => ['required', 'array'],
            'data.*' => ['nullable'],
        ];
    }
}

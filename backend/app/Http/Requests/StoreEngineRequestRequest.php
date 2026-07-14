<?php

namespace App\Http\Requests;

class StoreEngineRequestRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'workflow_version_id' => ['required', 'integer', 'exists:workflow_versions,id'],
            'merchant_id' => ['nullable', 'integer', 'exists:merchants,id'],
            'data' => ['present', 'array'],
            'data.*' => ['nullable'],
            'upload_tokens' => ['sometimes', 'array'],
            'upload_tokens.*' => ['string'],
            'comment' => ['nullable', 'string', 'max:2000'],
            // Not the authority for which transition executes — the server
            // always resolves its own canonical initial transition
            // (SubmitTransitionResolver) and only cross-checks this value if
            // present, rejecting a mismatch rather than trusting it.
            'diagnostic_transition_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}

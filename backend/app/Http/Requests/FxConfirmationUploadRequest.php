<?php

namespace App\Http\Requests;

use App\Models\EngineRequest;

class FxConfirmationUploadRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        /** @var EngineRequest $engineRequest */
        $engineRequest = $this->route('engineRequest');

        return $user->can('uploadSignedFx', $engineRequest);
    }

    public function rules(): array
    {
        return [
            'signed_document' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'signed_document.required' => 'يجب رفع وثيقة المصارفة الموقعة.',
            'signed_document.mimetypes' => 'يجب أن يكون الملف بصيغة PDF فقط.',
            'signed_document.max' => 'حجم الملف يتجاوز الحد الأقصى (10MB).',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\UserRole;

class FxConfirmationUploadRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Only COMMITTEE_DIRECTOR may upload signed FX confirmation documents
        return $user->role === UserRole::COMMITTEE_DIRECTOR;
    }

    public function rules(): array
    {
        return [
            'signed_document' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
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

<?php

namespace App\Http\Requests;

class UploadDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'confirmation_request' => ['sometimes', 'boolean'],
        ];
    }
}

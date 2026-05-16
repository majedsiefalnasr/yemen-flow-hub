<?php

namespace App\Http\Requests;

class UploadSwiftRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }
}

<?php

namespace App\Http\Requests;

class UploadRequestDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'integer', 'exists:import_requests,id'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }
}

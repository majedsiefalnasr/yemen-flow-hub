<?php

namespace App\Http\Requests;

use App\Models\ImportRequest;

class UploadRequestDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // CBY users (no bank_id) are not permitted to upload request documents.
        // Bank users must belong to the same bank as the request.
        $requestId = $this->input('request_id');
        if (! $requestId) {
            return false;
        }

        $importRequest = ImportRequest::find((int) $requestId);
        if (! $importRequest) {
            return false;
        }

        // CBY staff (no bank_id) may upload to any request
        if ($user->isCbyUser()) {
            return true;
        }

        // Bank users must belong to the same bank as the request
        return $user->bank_id !== null && $user->bank_id === $importRequest->bank_id;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'integer', 'exists:import_requests,id'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'confirmation_request' => ['sometimes', 'boolean'],
            'sub_type' => ['sometimes', 'nullable', 'string', 'in:proforma_invoice,commercial_register,tax_card,confirmation_request,extra_docs'],
        ];
    }
}

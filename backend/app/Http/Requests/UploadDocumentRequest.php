<?php

namespace App\Http\Requests;

use App\Support\UploadSizeLimit;

class UploadDocumentRequest extends ApiFormRequest
{
    public function __construct(
        private readonly UploadSizeLimit $uploadSizeLimit,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.$this->uploadSizeLimit->maxKilobytes()],
            'confirmation_request' => ['sometimes', 'boolean'],
        ];
    }
}

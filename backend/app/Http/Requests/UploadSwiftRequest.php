<?php

namespace App\Http\Requests;

use App\Support\UploadSizeLimit;

class UploadSwiftRequest extends ApiFormRequest
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
        $maxKb = $this->uploadSizeLimit->maxKilobytes();

        return [
            // Legacy mode (single SWIFT PDF)
            'file' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:'.$maxKb],
            // New package mode (SWIFT + FX request + reference)
            'swift_reference' => ['nullable', 'string', 'max:191', 'required_with:swift_file,fx_request_file'],
            'swift_file' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:'.$maxKb, 'required_with:swift_reference,fx_request_file'],
            'fx_request_file' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:'.$maxKb, 'required_with:swift_reference,swift_file'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasLegacyFile = $this->hasFile('file');
            $hasPackage = $this->hasFile('swift_file')
                || $this->hasFile('fx_request_file')
                || filled($this->input('swift_reference'));

            if (! $hasLegacyFile && ! $hasPackage) {
                $validator->errors()->add('file', 'Either legacy SWIFT file or full SWIFT package is required.');
            }
        });
    }
}

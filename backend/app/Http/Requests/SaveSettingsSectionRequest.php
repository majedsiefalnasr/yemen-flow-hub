<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'section' => 'required|string|in:workflow,email,security,general,theming,notif',
            'data' => 'required|array',
        ];
    }

    public function messages(): array
    {
        return [
            'section.required' => 'Section is required.',
            'section.in' => 'Invalid settings section.',
            'data.required' => 'Settings data is required.',
            'data.array' => 'Settings data must be an array.',
        ];
    }

    public function isSystemSection(): bool
    {
        return in_array($this->input('section'), ['workflow', 'email', 'security', 'general']);
    }

    public function isUserSection(): bool
    {
        return in_array($this->input('section'), ['theming', 'notif']);
    }
}

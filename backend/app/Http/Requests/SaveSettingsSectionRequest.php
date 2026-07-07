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
            'section' => 'required|string|in:workflow,security,general,theming,notif',
            'subsection' => 'nullable|string|in:appearance,branding,accessibility',
            'data' => 'required|array',
            'data.brandColor' => 'sometimes|string|regex:/^#[0-9a-fA-F]{6}$/',
            'data.brandLogoName' => 'sometimes|nullable|string|max:255',
            'data.brandLogoFile' => 'sometimes|nullable|file|mimes:png,svg,jpeg,webp|max:2048',
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
        return in_array($this->input('section'), ['workflow', 'security', 'general'], true)
            || ($this->input('section') === 'theming' && $this->input('subsection') === 'branding');
    }

    public function isUserSection(): bool
    {
        return in_array($this->input('section'), ['theming', 'notif']);
    }
}

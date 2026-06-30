<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => 'sometimes|in:ar,en',
            'dashboard_view' => 'sometimes|in:compact,normal,expanded',
            'table_density' => 'sometimes|in:compact,normal,comfortable',
            'page_size' => 'sometimes|in:10,25,50,100',
            'default_filters' => 'sometimes|array',
            'notification_preferences' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'language.in' => 'Language must be either ar or en.',
            'dashboard_view.in' => 'Dashboard view must be compact, normal, or expanded.',
            'table_density.in' => 'Table density must be compact, normal, or comfortable.',
            'page_size.in' => 'Page size must be 10, 25, 50, or 100.',
            'default_filters.array' => 'Default filters must be an array.',
            'notification_preferences.array' => 'Notification preferences must be an array.',
        ];
    }
}

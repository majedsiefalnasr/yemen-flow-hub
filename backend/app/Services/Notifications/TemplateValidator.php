<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use Illuminate\Validation\ValidationException;

class TemplateValidator
{
    public function __construct(private readonly NotificationRegistry $registry) {}

    /**
     * Save-time validation for admin-editable template definitions.
     *
     * @return array{subject: string, body: string}
     *
     * @throws ValidationException
     */
    public function validateForSave(NotificationType $type, string $subject, string $body): array
    {
        $allowedVariables = $this->registry->for($type)['allowed_variables'];
        $unknownVariables = array_values(array_diff(
            array_unique(array_merge($this->variablesIn($subject), $this->variablesIn($body))),
            $allowedVariables
        ));

        if ($unknownVariables !== []) {
            throw ValidationException::withMessages([
                'body' => 'Template contains unsupported variables: '.implode(', ', $unknownVariables),
            ]);
        }

        return [
            'subject' => trim(strip_tags($subject)),
            'body' => trim(strip_tags($body)),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function variablesIn(string $value): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $value, $matches);

        return $matches[1] ?? [];
    }
}

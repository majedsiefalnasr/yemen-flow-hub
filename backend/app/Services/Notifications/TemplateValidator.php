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

        $subjectUnknown = array_values(array_diff(array_unique($this->variablesIn($subject)), $allowedVariables));
        $bodyUnknown = array_values(array_diff(array_unique($this->variablesIn($body)), $allowedVariables));

        $errors = [];
        if ($subjectUnknown !== []) {
            $errors['subject'] = 'Template subject contains unsupported variables: '.implode(', ', $subjectUnknown);
        }
        if ($bodyUnknown !== []) {
            $errors['body'] = 'Template contains unsupported variables: '.implode(', ', $bodyUnknown);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $cleanSubject = trim($this->stripHtmlTags($subject));
        $cleanBody = trim($this->stripHtmlTags($body));

        $emptyErrors = [];
        if ($cleanSubject === '') {
            $emptyErrors['subject'] = 'Template subject cannot be empty after sanitization.';
        }
        if ($cleanBody === '') {
            $emptyErrors['body'] = 'Template body cannot be empty after sanitization.';
        }
        if ($emptyErrors !== []) {
            throw ValidationException::withMessages($emptyErrors);
        }

        return [
            'subject' => $cleanSubject,
            'body' => $cleanBody,
        ];
    }

    /**
     * Strip raw HTML tags while preserving Markdown source.
     *
     * Unlike strip_tags(), which deletes everything from a stray "<" to the next
     * ">" (or end of string) — corrupting legitimate Markdown such as "amount &lt; 1000"
     * — this removes only actual tag constructs (&lt;tag ...&gt; / &lt;/tag&gt;).
     */
    private function stripHtmlTags(string $value): string
    {
        return (string) preg_replace('/<\/?[a-zA-Z][^>]*>/', '', $value);
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

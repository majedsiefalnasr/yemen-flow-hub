<?php

namespace App\Services\Mail;

use App\Models\SystemSetting;

class EmailTemplateService
{
    public const ALLOWED_VARIABLES = [
        'user_name',
        'request_reference',
        'importer_name',
        'amount',
        'currency',
        'status',
        'action_url',
        'bank_name',
    ];

    private array $bladeMap = [
        'approved' => 'request-approved',
        'rejected' => 'request-rejected',
        'returned' => 'request-returned',
    ];

    private array $defaultSubjects = [
        'approved' => 'تمت الموافقة على طلبكم - Yemen Flow Hub',
        'rejected' => 'تم رفض طلبكم - Yemen Flow Hub',
        'returned' => 'تم إعادة طلبكم للتعديل - Yemen Flow Hub',
    ];

    public function render(string $type, array $variables): array
    {
        $emailSettings = SystemSetting::getValueByKey('settings.email', []);
        $templates = is_array($emailSettings['templates'] ?? null) ? $emailSettings['templates'] : [];
        $dbTemplate = $templates[$type] ?? null;

        if ($dbTemplate && is_array($dbTemplate)) {
            $subject = $dbTemplate['subject'] ?? '';
            $body = $dbTemplate['body'] ?? '';

            // Build single-pass replacement maps so a substituted value can never
            // introduce or collide with another {{placeholder}} (no smuggling).
            // The body is delivered as raw HTML (htmlString), so every substituted
            // value is HTML-escaped to prevent injection from user-controlled
            // request data (supplier/importer name, bank name, comments). The
            // subject is plain text and is substituted without HTML escaping.
            $subjectMap = [];
            $bodyMap = [];
            foreach ($variables as $key => $value) {
                if (is_string($value)) {
                    $subjectMap['{{'.$key.'}}'] = $value;
                    $bodyMap['{{'.$key.'}}'] = e($value);
                }
            }

            $subject = strtr($subject, $subjectMap);
            $body = strtr($body, $bodyMap);

            $body = preg_replace('/\{\{[^}]+\}\}/', '', $body) ?? $body;
            $subject = preg_replace('/\{\{[^}]+\}\}/', '', $subject) ?? $subject;

            return ['subject' => $subject, 'body' => $body, 'source' => 'db'];
        }

        return [
            'subject' => $this->defaultSubjects[$type] ?? 'Yemen Flow Hub',
            'body' => view('emails.'.($this->bladeMap[$type] ?? $type), $variables)->render(),
            'source' => 'blade',
        ];
    }
}

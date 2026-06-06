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

            foreach ($variables as $key => $value) {
                if (is_string($value)) {
                    $subject = str_replace('{{'.$key.'}}', $value, $subject);
                    $body = str_replace('{{'.$key.'}}', $value, $body);
                }
            }

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

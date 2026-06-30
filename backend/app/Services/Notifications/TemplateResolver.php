<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;

class TemplateResolver
{
    private const BLADE_MAP = [
        NotificationType::REQUEST_APPROVED->value => 'request-approved',
        NotificationType::REQUEST_REJECTED->value => 'request-rejected',
        NotificationType::REQUEST_RETURNED->value => 'request-returned',
        NotificationType::VOTING_OPENED->value => 'voting-opened',
        NotificationType::MFA_OTP->value => 'system.mfa-otp',
        NotificationType::PASSWORD_RESET->value => 'system.password-recovery-otp',
    ];

    private const DEFAULT_SUBJECTS = [
        NotificationType::REQUEST_APPROVED->value => 'تمت الموافقة على طلبكم - The National Committee for Regulating & Financing Imports',
        NotificationType::REQUEST_REJECTED->value => 'تم رفض طلبكم - The National Committee for Regulating & Financing Imports',
        NotificationType::REQUEST_RETURNED->value => 'تم إعادة طلبكم للتعديل - The National Committee for Regulating & Financing Imports',
        NotificationType::VOTING_OPENED->value => 'تم فتح جلسة التصويت - The National Committee for Regulating & Financing Imports',
        NotificationType::MFA_OTP->value => 'رمز التحقق متعدد العوامل - The National Committee for Regulating & Financing Imports',
        NotificationType::PASSWORD_RESET->value => 'رمز استعادة كلمة المرور - The National Committee for Regulating & Financing Imports',
    ];

    public function __construct(private readonly NotificationRegistry $registry) {}

    /**
     * @return array{
     *     source: 'db'|'blade',
     *     subject: string,
     *     body: string|null,
     *     view: string|null,
     *     template_version_id: int|null,
     *     notification_type: string,
     *     allowed_variables: array<int, string>
     * }
     */
    public function resolve(NotificationType $type): array
    {
        $definition = $this->registry->for($type);

        if ($definition['source'] === 'db') {
            $template = NotificationTemplate::query()
                ->where('notification_type', $type->value)
                ->where('is_active', true)
                ->with('activeVersion')
                ->first();

            if ($template?->activeVersion) {
                return [
                    'source' => 'db',
                    'subject' => $template->activeVersion->subject,
                    'body' => $template->activeVersion->body,
                    'view' => null,
                    'template_version_id' => $template->activeVersion->id,
                    'notification_type' => $type->value,
                    'allowed_variables' => $definition['allowed_variables'],
                ];
            }
        }

        return [
            'source' => 'blade',
            'subject' => self::DEFAULT_SUBJECTS[$type->value] ?? 'The National Committee for Regulating & Financing Imports',
            'body' => null,
            'view' => 'emails.'.(self::BLADE_MAP[$type->value] ?? strtolower($type->value)),
            'template_version_id' => null,
            'notification_type' => $type->value,
            'allowed_variables' => $definition['allowed_variables'],
        ];
    }
}

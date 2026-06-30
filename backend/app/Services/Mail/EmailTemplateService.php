<?php

namespace App\Services\Mail;

use App\Enums\NotificationType;
use App\Services\Notifications\TemplateRenderer;

/**
 * @deprecated Story 15.3 compatibility shim. New code should use
 *             App\Services\Notifications\TemplateRenderer directly.
 */
class EmailTemplateService
{
    public const ALLOWED_VARIABLES = [
        'user_name',
        'reference_number',
        'importer_name',
        'amount',
        'currency',
        'status',
        'action_url',
        'bank_name',
    ];

    private array $typeMap = [
        'approved' => NotificationType::REQUEST_APPROVED,
        'rejected' => NotificationType::REQUEST_REJECTED,
        'returned' => NotificationType::REQUEST_RETURNED,
    ];

    public function __construct(private readonly TemplateRenderer $renderer) {}

    public function render(string $type, array $variables): array
    {
        $notificationType = $this->typeMap[$type] ?? NotificationType::tryFrom($type);

        if (! $notificationType) {
            // Fail loudly instead of silently rendering an approval email for an
            // unknown alias (which would send the wrong message to recipients).
            throw new \InvalidArgumentException("Unknown email template type [{$type}].");
        }

        if (! array_key_exists('reference_number', $variables) && array_key_exists('request_reference', $variables)) {
            $variables['reference_number'] = $variables['request_reference'];
        }

        $rendered = $this->renderer->render($notificationType, $variables);

        return $rendered + ['body' => $rendered['html']];
    }
}

<?php

namespace App\Enums;

use App\Services\Notifications\NotificationRegistry;

/**
 * Canonical notification types for the formalized email subsystem (Epic 15).
 *
 * Phase-1 cases only. Every case MUST have a matching entry in
 * {@see NotificationRegistry} (enforced by tests).
 */
enum NotificationType: string
{
    case REQUEST_APPROVED = 'REQUEST_APPROVED';
    case REQUEST_REJECTED = 'REQUEST_REJECTED';
    case REQUEST_RETURNED = 'REQUEST_RETURNED';
    case VOTING_OPENED = 'VOTING_OPENED';
    case MFA_OTP = 'MFA_OTP';
    case PASSWORD_RESET = 'PASSWORD_RESET';
}

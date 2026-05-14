<?php

namespace App\Services\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;

class TransitionMap
{
    public static function definitions(): array
    {
        return [
            'submit' => [
                'from' => [RequestStatus::DRAFT, RequestStatus::RETURNED_TO_DATA_ENTRY],
                'to' => RequestStatus::SUBMITTED,
                'roles' => [UserRole::DATA_ENTRY, UserRole::BANK_MANAGER],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'bank_approve' => [
                'from' => [RequestStatus::SUBMITTED],
                'to' => RequestStatus::BANK_APPROVED,
                'roles' => [UserRole::BANK_REVIEWER, UserRole::BANK_MANAGER],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'bank_reject' => [
                'from' => [RequestStatus::SUBMITTED],
                'to' => RequestStatus::BANK_REJECTED,
                'roles' => [UserRole::BANK_REVIEWER, UserRole::BANK_MANAGER],
                'next_owner' => null,
            ],
            'return_to_entry' => [
                'from' => [RequestStatus::SUBMITTED, RequestStatus::SUPPORT_REJECTED, RequestStatus::EXECUTIVE_REJECTED],
                'to' => RequestStatus::RETURNED_TO_DATA_ENTRY,
                'roles' => [UserRole::BANK_REVIEWER, UserRole::BANK_MANAGER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'support_claim' => [
                'from' => [RequestStatus::BANK_APPROVED, RequestStatus::SUPPORT_UNDER_REVIEW],
                'to' => RequestStatus::SUPPORT_UNDER_REVIEW,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'support_release' => [
                'from' => [RequestStatus::SUPPORT_UNDER_REVIEW],
                'to' => RequestStatus::BANK_APPROVED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'support_approve' => [
                'from' => [RequestStatus::SUPPORT_UNDER_REVIEW],
                'to' => RequestStatus::SUPPORT_APPROVED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SWIFT_OFFICER,
            ],
            'support_reject' => [
                'from' => [RequestStatus::SUPPORT_UNDER_REVIEW],
                'to' => RequestStatus::SUPPORT_REJECTED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'swift_upload' => [
                'from' => [RequestStatus::SUPPORT_APPROVED],
                'to' => RequestStatus::SWIFT_UPLOADED,
                'roles' => [UserRole::SWIFT_OFFICER, UserRole::BANK_MANAGER],
                'next_owner' => UserRole::EXECUTIVE_MEMBER,
            ],
            'start_voting' => [
                'from' => [RequestStatus::SWIFT_UPLOADED],
                'to' => RequestStatus::EXECUTIVE_VOTING,
                'roles' => [UserRole::EXECUTIVE_MEMBER],
                'next_owner' => UserRole::EXECUTIVE_MEMBER,
            ],
            'finalize_approved' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING],
                'to' => RequestStatus::EXECUTIVE_APPROVED,
                'roles' => [UserRole::EXECUTIVE_DIRECTOR],
                'next_owner' => UserRole::EXECUTIVE_DIRECTOR,
            ],
            'finalize_rejected' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING],
                'to' => RequestStatus::EXECUTIVE_REJECTED,
                'roles' => [UserRole::EXECUTIVE_DIRECTOR],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'issue_customs' => [
                'from' => [RequestStatus::EXECUTIVE_APPROVED],
                'to' => RequestStatus::CUSTOMS_ISSUED,
                'roles' => [UserRole::EXECUTIVE_DIRECTOR],
                'next_owner' => UserRole::EXECUTIVE_DIRECTOR,
            ],
            'complete' => [
                'from' => [RequestStatus::CUSTOMS_ISSUED],
                'to' => RequestStatus::COMPLETED,
                'roles' => [UserRole::EXECUTIVE_DIRECTOR],
                'next_owner' => null,
            ],
        ];
    }
}

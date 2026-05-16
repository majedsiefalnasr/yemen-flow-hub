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
                'from' => [RequestStatus::DRAFT, RequestStatus::DRAFT_REJECTED_INTERNAL],
                'to' => RequestStatus::SUBMITTED,
                'roles' => [UserRole::DATA_ENTRY],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'bank_begin_review' => [
                'from' => [RequestStatus::SUBMITTED],
                'to' => RequestStatus::BANK_REVIEW,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'bank_approve' => [
                'from' => [RequestStatus::BANK_REVIEW],
                'to' => RequestStatus::BANK_APPROVED,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'bank_reject' => [
                'from' => [RequestStatus::BANK_REVIEW],
                'to' => RequestStatus::DRAFT_REJECTED_INTERNAL,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'return_to_entry' => [
                'from' => [RequestStatus::SUPPORT_REJECTED],
                'to' => RequestStatus::DRAFT_REJECTED_INTERNAL,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'bank_return_after_support_reject' => [
                'from' => [RequestStatus::SUPPORT_REJECTED],
                'to' => RequestStatus::DRAFT_REJECTED_INTERNAL,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::DATA_ENTRY,
            ],
            'bank_finalize_rejection' => [
                'from' => [RequestStatus::SUPPORT_REJECTED],
                'to' => RequestStatus::SUPPORT_REJECTED,
                'roles' => [UserRole::BANK_REVIEWER],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'support_claim' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_PENDING],
                'to' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'support_release' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_REVIEW_PENDING,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SUPPORT_COMMITTEE,
            ],
            'support_approve' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_APPROVED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::SWIFT_OFFICER,
            ],
            'support_reject' => [
                'from' => [RequestStatus::SUPPORT_REVIEW_IN_PROGRESS],
                'to' => RequestStatus::SUPPORT_REJECTED,
                'roles' => [UserRole::SUPPORT_COMMITTEE],
                'next_owner' => UserRole::BANK_REVIEWER,
            ],
            'swift_upload' => [
                'from' => [RequestStatus::WAITING_FOR_SWIFT],
                'to' => RequestStatus::SWIFT_UPLOADED,
                'roles' => [UserRole::SWIFT_OFFICER],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'open_voting' => [
                'from' => [RequestStatus::WAITING_FOR_VOTING_OPEN],
                'to' => RequestStatus::EXECUTIVE_VOTING_OPEN,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::EXECUTIVE_MEMBER,
            ],
            'close_voting' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING_OPEN],
                'to' => RequestStatus::EXECUTIVE_VOTING_CLOSED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'finalize_approved' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING_CLOSED],
                'to' => RequestStatus::EXECUTIVE_APPROVED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'finalize_rejected' => [
                'from' => [RequestStatus::EXECUTIVE_VOTING_CLOSED],
                'to' => RequestStatus::EXECUTIVE_REJECTED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => null,
            ],
            'issue_customs' => [
                'from' => [RequestStatus::EXECUTIVE_APPROVED],
                'to' => RequestStatus::CUSTOMS_DECLARATION_ISSUED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => UserRole::COMMITTEE_DIRECTOR,
            ],
            'complete' => [
                'from' => [RequestStatus::CUSTOMS_DECLARATION_ISSUED],
                'to' => RequestStatus::COMPLETED,
                'roles' => [UserRole::COMMITTEE_DIRECTOR],
                'next_owner' => null,
            ],
        ];
    }
}

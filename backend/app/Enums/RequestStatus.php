<?php

namespace App\Enums;

enum RequestStatus: string
{
    case DRAFT = 'DRAFT';
    case DRAFT_REJECTED_INTERNAL = 'DRAFT_REJECTED_INTERNAL';
    case SUBMITTED = 'SUBMITTED';
    case BANK_REVIEW = 'BANK_REVIEW';
    case BANK_APPROVED = 'BANK_APPROVED';
    case SUPPORT_REVIEW_PENDING = 'SUPPORT_REVIEW_PENDING';
    case SUPPORT_REVIEW_IN_PROGRESS = 'SUPPORT_REVIEW_IN_PROGRESS';
    case SUPPORT_APPROVED = 'SUPPORT_APPROVED';
    case SUPPORT_REJECTED = 'SUPPORT_REJECTED';
    case WAITING_FOR_SWIFT = 'WAITING_FOR_SWIFT';
    case SWIFT_UPLOADED = 'SWIFT_UPLOADED';
    case WAITING_FOR_VOTING_OPEN = 'WAITING_FOR_VOTING_OPEN';
    case EXECUTIVE_VOTING_OPEN = 'EXECUTIVE_VOTING_OPEN';
    case EXECUTIVE_VOTING_CLOSED = 'EXECUTIVE_VOTING_CLOSED';
    case EXECUTIVE_APPROVED = 'EXECUTIVE_APPROVED';
    case EXECUTIVE_REJECTED = 'EXECUTIVE_REJECTED';
    case FX_CONFIRMATION_PENDING = 'FX_CONFIRMATION_PENDING';
    case CUSTOMS_DECLARATION_ISSUED = 'CUSTOMS_DECLARATION_ISSUED';
    case COMPLETED = 'COMPLETED';
    case BANK_RETURNED = 'BANK_RETURNED';
    case SUPPORT_RETURNED = 'SUPPORT_RETURNED';
    case BANK_REJECTED = 'BANK_REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة / Draft',
            self::DRAFT_REJECTED_INTERNAL => 'معاد للتعديل / Returned for Correction',
            self::SUBMITTED => 'مقدّم للمراجعة / Submitted',
            self::BANK_REVIEW => 'قيد المراجعة الداخلية / Under Bank Review',
            self::BANK_APPROVED => 'موافقة البنك / Bank Approved',
            self::SUPPORT_REVIEW_PENDING => 'بانتظار لجنة الدعم / Awaiting Support Review',
            self::SUPPORT_REVIEW_IN_PROGRESS => 'قيد مراجعة لجنة الدعم / Support Review In Progress',
            self::SUPPORT_APPROVED => 'موافقة لجنة الدعم / Support Approved',
            self::SUPPORT_REJECTED => 'رفض لجنة الدعم / Support Rejected',
            self::WAITING_FOR_SWIFT => 'بانتظار رفع السويفت / Waiting for SWIFT',
            self::SWIFT_UPLOADED => 'تم رفع السويفت / SWIFT Uploaded',
            self::WAITING_FOR_VOTING_OPEN => 'بانتظار فتح التصويت / Waiting for Voting Open',
            self::EXECUTIVE_VOTING_OPEN => 'جلسة التصويت مفتوحة / Executive Voting Open',
            self::EXECUTIVE_VOTING_CLOSED => 'جلسة التصويت مغلقة / Executive Voting Closed',
            self::EXECUTIVE_APPROVED => 'موافقة تنفيذية / Executive Approved',
            self::EXECUTIVE_REJECTED => 'رفض تنفيذي نهائي / Executive Rejected',
            self::FX_CONFIRMATION_PENDING => 'بانتظار إصدار المصارفة الخارجية / FX Confirmation Pending',
            self::CUSTOMS_DECLARATION_ISSUED => 'صدر البيان الجمركي / Customs Declaration Issued',
            self::COMPLETED => 'مكتمل / Completed',
            self::BANK_RETURNED => 'إعادة للمدخل / Returned to Intake',
            self::SUPPORT_RETURNED => 'إعادة من المساندة / Returned from Support',
            self::BANK_REJECTED => 'مرفوض (البنك) / Bank Rejected',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::EXECUTIVE_REJECTED,
            self::CUSTOMS_DECLARATION_ISSUED,
            self::COMPLETED,
            self::BANK_REJECTED,
        ], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::DRAFT_REJECTED_INTERNAL,
            self::BANK_RETURNED,
            self::SUPPORT_RETURNED,
        ], true);
    }
}

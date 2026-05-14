<?php

namespace App\Enums;

enum RequestStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case BANK_APPROVED = 'BANK_APPROVED';
    case BANK_REJECTED = 'BANK_REJECTED';
    case RETURNED_TO_DATA_ENTRY = 'RETURNED_TO_DATA_ENTRY';
    case SUPPORT_UNDER_REVIEW = 'SUPPORT_UNDER_REVIEW';
    case SUPPORT_APPROVED = 'SUPPORT_APPROVED';
    case SUPPORT_REJECTED = 'SUPPORT_REJECTED';
    case SWIFT_UPLOADED = 'SWIFT_UPLOADED';
    case EXECUTIVE_VOTING = 'EXECUTIVE_VOTING';
    case EXECUTIVE_APPROVED = 'EXECUTIVE_APPROVED';
    case EXECUTIVE_REJECTED = 'EXECUTIVE_REJECTED';
    case CUSTOMS_ISSUED = 'CUSTOMS_ISSUED';
    case COMPLETED = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft / مسودة',
            self::SUBMITTED => 'Submitted / تم التقديم',
            self::BANK_APPROVED => 'Bank Approved / موافقة البنك',
            self::BANK_REJECTED => 'Bank Rejected / رفض البنك',
            self::RETURNED_TO_DATA_ENTRY => 'Returned to Data Entry / إرجاع للإدخال',
            self::SUPPORT_UNDER_REVIEW => 'قيد مراجعة لجنة الدعم / Under Support Review',
            self::SUPPORT_APPROVED => 'Support Approved / موافقة لجنة الدعم',
            self::SUPPORT_REJECTED => 'Support Rejected / رفض لجنة الدعم',
            self::SWIFT_UPLOADED => 'SWIFT Uploaded / تم رفع السويفت',
            self::EXECUTIVE_VOTING => 'Executive Voting / تصويت تنفيذي',
            self::EXECUTIVE_APPROVED => 'Executive Approved / موافقة تنفيذية',
            self::EXECUTIVE_REJECTED => 'Executive Rejected / رفض تنفيذي',
            self::CUSTOMS_ISSUED => 'Customs Issued / إصدار الجمارك',
            self::COMPLETED => 'Completed / مكتمل',
        };
    }
}


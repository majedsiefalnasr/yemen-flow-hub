<?php

namespace App\Enums;

enum AuditAction: string
{
    case LOGIN = 'LOGIN';
    case LOGOUT = 'LOGOUT';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case REQUEST_CREATED = 'REQUEST_CREATED';
    case REQUEST_UPDATED = 'REQUEST_UPDATED';
    case REQUEST_DELETED = 'REQUEST_DELETED';
    case REQUEST_ABANDONED = 'REQUEST_ABANDONED';
    case STATUS_TRANSITION = 'STATUS_TRANSITION';
    case VOTE_CAST = 'VOTE_CAST';
    case DOCUMENT_UPLOADED = 'DOCUMENT_UPLOADED';
    case DOCUMENT_DOWNLOADED = 'DOCUMENT_DOWNLOADED';
    case DOCUMENT_DELETED = 'DOCUMENT_DELETED';
    case SWIFT_UPLOADED = 'SWIFT_UPLOADED';
    case CUSTOMS_ISSUED = 'CUSTOMS_ISSUED';
    case FX_CONFIRMATION_UPLOADED = 'FX_CONFIRMATION_UPLOADED';
    case FX_CONFIRMATION_ISSUED = 'FX_CONFIRMATION_ISSUED';
    case USER_CREATED = 'USER_CREATED';
    case USER_UPDATED = 'USER_UPDATED';
    case USER_DEACTIVATED = 'USER_DEACTIVATED';
    case BANK_CREATED = 'BANK_CREATED';
    case BANK_UPDATED = 'BANK_UPDATED';
    case PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    case PASSWORD_CHANGE_FAILED = 'PASSWORD_CHANGE_FAILED';
    case PASSWORD_RESET = 'PASSWORD_RESET';
    case MFA_RESET = 'MFA_RESET';
    case PIN_SET = 'PIN_SET';
    case PIN_CHANGED = 'PIN_CHANGED';
    case PIN_DISABLED = 'PIN_DISABLED';
    case PIN_RESET = 'PIN_RESET';
    case ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    case SESSIONS_REVOKED = 'SESSIONS_REVOKED';
    case RECOVERY_CODES_REGENERATED = 'RECOVERY_CODES_REGENERATED';
    case SETTINGS_UPDATED = 'SETTINGS_UPDATED';
    case AUTHORIZATION_FAILURE = 'AUTHORIZATION_FAILURE';
    case REPORT_EXPORTED = 'REPORT_EXPORTED';
    case CLAIM_ACQUIRED = 'CLAIM_ACQUIRED';
    case CLAIM_RELEASED = 'CLAIM_RELEASED';
    case EMAIL_TEST_SENT = 'EMAIL_TEST_SENT';
    case EMAIL_TEMPLATE_UPDATED = 'EMAIL_TEMPLATE_UPDATED';
    case EMAIL_DELIVERY_FAILED = 'EMAIL_DELIVERY_FAILED';
    case GOVERNANCE_CREATED = 'GOVERNANCE_CREATED';
    case GOVERNANCE_UPDATED = 'GOVERNANCE_UPDATED';
    case GOVERNANCE_DELETED = 'GOVERNANCE_DELETED';
    case WORKFLOW_CLONED = 'WORKFLOW_CLONED';
    case WORKFLOW_VALIDATED = 'WORKFLOW_VALIDATED';
    case WORKFLOW_PUBLISHED = 'WORKFLOW_PUBLISHED';
    case AUDIT_LOG_EXPORTED = 'AUDIT_LOG_EXPORTED';
    case SCREEN_PERMISSION_UPDATED = 'SCREEN_PERMISSION_UPDATED';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Login / تسجيل الدخول',
            self::LOGOUT => 'Logout / تسجيل الخروج',
            self::LOGIN_FAILED => 'Login Failed / فشل تسجيل الدخول',
            self::REQUEST_CREATED => 'Request Created / إنشاء طلب',
            self::REQUEST_UPDATED => 'Request Updated / تحديث طلب',
            self::REQUEST_DELETED => 'Request Deleted / حذف طلب',
            self::REQUEST_ABANDONED => 'Request Abandoned / إلغاء مسودة',
            self::STATUS_TRANSITION => 'Status Transition / انتقال الحالة',
            self::VOTE_CAST => 'Vote Cast / تسجيل تصويت',
            self::DOCUMENT_UPLOADED => 'Document Uploaded / رفع مستند',
            self::DOCUMENT_DOWNLOADED => 'Document Downloaded / تنزيل مستند',
            self::DOCUMENT_DELETED => 'Document Deleted / حذف مستند',
            self::SWIFT_UPLOADED => 'SWIFT Uploaded / رفع سويفت',
            self::CUSTOMS_ISSUED => 'Customs Issued / إصدار الجمارك',
            self::FX_CONFIRMATION_UPLOADED => 'FX Confirmation Uploaded / رفع وثيقة المصارفة الخارجية',
            self::FX_CONFIRMATION_ISSUED => 'FX Confirmation Issued / إصدار وثيقة المصارفة الخارجية',
            self::USER_CREATED => 'User Created / إنشاء مستخدم',
            self::USER_UPDATED => 'User Updated / تحديث مستخدم',
            self::USER_DEACTIVATED => 'User Deactivated / إيقاف مستخدم',
            self::BANK_CREATED => 'Bank Created / إنشاء بنك',
            self::BANK_UPDATED => 'Bank Updated / تحديث بنك',
            self::PASSWORD_CHANGED => 'Password Changed / تغيير كلمة المرور',
            self::PASSWORD_CHANGE_FAILED => 'Password Change Failed / فشل تغيير كلمة المرور',
            self::PASSWORD_RESET => 'Password Reset / إعادة تعيين كلمة المرور',
            self::MFA_RESET => 'MFA Reset / إعادة ضبط المصادقة متعددة العوامل',
            self::PIN_SET => 'PIN Set / تعيين رمز PIN',
            self::PIN_CHANGED => 'PIN Changed / تغيير رمز PIN',
            self::PIN_DISABLED => 'PIN Disabled / تعطيل رمز PIN',
            self::PIN_RESET => 'PIN Reset / إعادة ضبط رمز PIN',
            self::ACCOUNT_LOCKED => 'Account Locked / قفل الحساب',
            self::SESSIONS_REVOKED => 'Sessions Revoked / إلغاء الجلسات',
            self::RECOVERY_CODES_REGENERATED => 'Recovery Codes Regenerated / إعادة إنشاء رموز الاستعادة',
            self::SETTINGS_UPDATED => 'Settings Updated / تحديث الإعدادات',
            self::AUTHORIZATION_FAILURE => 'Authorization Failure / فشل التفويض',
            self::REPORT_EXPORTED => 'Report Exported / تصدير تقرير',
            self::CLAIM_ACQUIRED => 'Claim Acquired / تأكيد المطالبة',
            self::CLAIM_RELEASED => 'Claim Released / إلغاء المطالبة',
            self::EMAIL_TEST_SENT => 'Email Test Sent / إرسال بريد اختباري',
            self::EMAIL_TEMPLATE_UPDATED => 'Email Template Updated / تحديث قالب البريد',
            self::EMAIL_DELIVERY_FAILED => 'Email Delivery Failed / فشل إرسال البريد الإلكتروني',
            self::GOVERNANCE_CREATED => 'Governance Created / إنشاء سجل حوكمة',
            self::GOVERNANCE_UPDATED => 'Governance Updated / تحديث سجل حوكمة',
            self::GOVERNANCE_DELETED => 'Governance Deleted / حذف سجل حوكمة',
            self::WORKFLOW_CLONED => 'Workflow Cloned / استنساخ سير العمل',
            self::WORKFLOW_VALIDATED => 'Workflow Validated / التحقق من سير العمل',
            self::WORKFLOW_PUBLISHED => 'Workflow Published / نشر سير العمل',
            self::AUDIT_LOG_EXPORTED => 'Audit Log Exported / تصدير سجل التدقيق',
            self::SCREEN_PERMISSION_UPDATED => 'Screen Permission Updated / تحديث صلاحيات الشاشة',
        };
    }
}

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
    case STATUS_TRANSITION = 'STATUS_TRANSITION';
    case VOTE_CAST = 'VOTE_CAST';
    case DOCUMENT_UPLOADED = 'DOCUMENT_UPLOADED';
    case DOCUMENT_DOWNLOADED = 'DOCUMENT_DOWNLOADED';
    case SWIFT_UPLOADED = 'SWIFT_UPLOADED';
    case CUSTOMS_ISSUED = 'CUSTOMS_ISSUED';
    case USER_CREATED = 'USER_CREATED';
    case USER_UPDATED = 'USER_UPDATED';
    case USER_DEACTIVATED = 'USER_DEACTIVATED';
    case BANK_UPDATED = 'BANK_UPDATED';
    case PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    case SETTINGS_UPDATED = 'SETTINGS_UPDATED';
    case AUTHORIZATION_FAILURE = 'AUTHORIZATION_FAILURE';
    case REPORT_EXPORTED = 'REPORT_EXPORTED';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Login / تسجيل الدخول',
            self::LOGOUT => 'Logout / تسجيل الخروج',
            self::LOGIN_FAILED => 'Login Failed / فشل تسجيل الدخول',
            self::REQUEST_CREATED => 'Request Created / إنشاء طلب',
            self::REQUEST_UPDATED => 'Request Updated / تحديث طلب',
            self::REQUEST_DELETED => 'Request Deleted / حذف طلب',
            self::STATUS_TRANSITION => 'Status Transition / انتقال الحالة',
            self::VOTE_CAST => 'Vote Cast / تسجيل تصويت',
            self::DOCUMENT_UPLOADED => 'Document Uploaded / رفع مستند',
            self::DOCUMENT_DOWNLOADED => 'Document Downloaded / تنزيل مستند',
            self::SWIFT_UPLOADED => 'SWIFT Uploaded / رفع سويفت',
            self::CUSTOMS_ISSUED => 'Customs Issued / إصدار الجمارك',
            self::USER_CREATED => 'User Created / إنشاء مستخدم',
            self::USER_UPDATED => 'User Updated / تحديث مستخدم',
            self::USER_DEACTIVATED => 'User Deactivated / إيقاف مستخدم',
            self::BANK_UPDATED => 'Bank Updated / تحديث بنك',
            self::PASSWORD_CHANGED => 'Password Changed / تغيير كلمة المرور',
            self::SETTINGS_UPDATED => 'Settings Updated / تحديث الإعدادات',
            self::AUTHORIZATION_FAILURE => 'Authorization Failure / فشل التفويض',
            self::REPORT_EXPORTED => 'Report Exported / تصدير تقرير',
        };
    }
}

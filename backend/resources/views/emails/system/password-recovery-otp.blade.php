@php
    $displayCode = $otp_code ?? $otp ?? '';
    $displayTtl = $ttl_minutes ?? $ttlMinutes ?? '';
@endphp

<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>رمز استعادة كلمة المرور</title>
</head>
<body style="margin: 0; background-color: {{ config('email-theme.background') }}; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; line-height: 1.8; direction: rtl;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; background-color: {{ config('email-theme.background') }};">
        <tr>
            <td align="center" style="padding: 24px;">
                <table width="640" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 640px; width: 100%; background-color: {{ config('email-theme.surface') }}; border: 1px solid {{ config('email-theme.border') }}; border-radius: 16px;">
                    <tr>
                        <td style="padding: 24px;">
                            <p dir="rtl" style="margin: 0 0 16px 0; color: {{ config('email-theme.primary_text') }}; font-size: 16px;">مرحباً {{ $user_name ?? '' }}،</p>
                            <p dir="rtl" style="margin: 0 0 16px 0; color: {{ config('email-theme.primary_text') }}; font-size: 16px;">تم طلب استعادة كلمة المرور لحسابكم في منصة اللجنة الوطنية لتنظيم وتمويل الواردات. استخدموا رمز الاستعادة التالي لإكمال العملية.</p>
                            <x-email.otp-code :code="$displayCode" />
                            <p dir="rtl" style="margin: 0 0 16px 0; color: {{ config('email-theme.primary_text') }}; font-size: 16px;">ينتهي هذا الرمز خلال {{ $displayTtl }} دقائق، ويستخدم مرة واحدة فقط.</p>
                            <p dir="rtl" style="margin: 0 0 24px 0; color: {{ config('email-theme.locked_gray') }}; font-size: 14px;">إذا لم تطلبوا هذا الإجراء، يمكن تجاهل هذه الرسالة بأمان.</p>
                            <x-email.confidentiality-notice />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

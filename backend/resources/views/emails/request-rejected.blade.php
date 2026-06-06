<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تم رفض الطلب</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.7; direction: rtl;">
    <p>عزيزي {{ $requestModel->creator?->name ?? 'المستخدم' }}،</p>
    @if($terminal)
        <p>نأسف لإبلاغكم بأنه تم رفض طلبكم نهائياً في منصة Yemen Flow Hub.</p>
    @else
        <p>نأسف لإبلاغكم بأنه تم رفض طلبكم في منصة Yemen Flow Hub.</p>
    @endif
    <p><strong>رقم الطلب:</strong> {{ $requestModel->reference_number }}</p>
    <p><strong>المبلغ:</strong> <span dir="ltr">{{ number_format($requestModel->amount, 2) }} {{ $requestModel->currency }}</span></p>
    <p><strong>المورد:</strong> {{ $requestModel->supplier_name }}</p>
    @if($comment)
        <p><strong>السبب:</strong> {{ $comment }}</p>
    @endif
    <p>
        <a href="{{ config('app.url') }}/requests/{{ $requestModel->id }}">عرض الطلب</a>
    </p>
    <p>شكراً لاستخدامكم منصة Yemen Flow Hub.</p>
</body>
</html>

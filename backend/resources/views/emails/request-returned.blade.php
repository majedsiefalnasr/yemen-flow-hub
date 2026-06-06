<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تم إعادة الطلب للتعديل</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.7; direction: rtl;">
    <p>عزيزي {{ $requestModel->creator?->name ?? 'المستخدم' }}،</p>
    <p>تم إعادة طلبكم للتعديل في منصة Yemen Flow Hub.</p>
    <p><strong>رقم الطلب:</strong> {{ $requestModel->reference_number }}</p>
    <p><strong>المبلغ:</strong> <span dir="ltr">{{ number_format($requestModel->amount, 2) }} {{ $requestModel->currency }}</span></p>
    <p><strong>المورد:</strong> {{ $requestModel->supplier_name }}</p>
    @if($fromRole)
        <p><strong>أُعيد بواسطة:</strong> {{ $fromRole }}</p>
    @endif
    @if($comment)
        <p><strong>ملاحظات:</strong> {{ $comment }}</p>
    @endif
    <p>
        <a href="{{ config('app.url') }}/requests/{{ $requestModel->id }}">عرض الطلب وتعديله</a>
    </p>
    <p>شكراً لاستخدامكم منصة Yemen Flow Hub.</p>
</body>
</html>

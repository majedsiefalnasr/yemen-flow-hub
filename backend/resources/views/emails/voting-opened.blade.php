<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تم فتح جلسة التصويت</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.7; direction: rtl;">
    <p>عزيزي العضو،</p>
    <p>تم فتح جلسة التصويت لطلب جديد في منصة اللجنة الوطنية لتنظيم وتمويل الواردات. يُرجى مراجعة الطلب والإدلاء بصوتكم.</p>
    <p><strong>رقم الطلب:</strong> {{ $requestModel->reference_number }}</p>
    <p><strong>المبلغ:</strong> <span dir="ltr">{{ number_format($requestModel->amount, 2) }} {{ $requestModel->currency }}</span></p>
    <p><strong>المورد:</strong> {{ $requestModel->supplier_name }}</p>
    <p>
        <a href="{{ config('app.url') }}/requests/{{ $requestModel->id }}">عرض الطلب والتصويت</a>
    </p>
    <p>شكراً لاستخدامكم منصة اللجنة الوطنية لتنظيم وتمويل الواردات.</p>
</body>
</html>

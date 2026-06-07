<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>وثيقة تأكيد مصارفة خارجية</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; direction: rtl; text-align: right; font-size: 13px; color: #111; line-height: 1.55; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #222; padding-bottom: 14px; }
        .logo { width: 70px; height: 70px; border: 1px solid #999; margin: 0 auto 8px; text-align: center; line-height: 70px; font-size: 11px; }
        h1 { margin: 6px 0; font-size: 22px; }
        .meta { margin-bottom: 16px; }
        .meta p { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #222; padding: 7px; vertical-align: top; }
        th { background: #f3f3f3; width: 30%; }
        .notice { border: 1px solid #222; padding: 10px 12px; margin: 14px 0; background: #fafafa; }
        .signatures { margin-top: 28px; }
        .signature-grid { width: 100%; border-collapse: collapse; }
        .signature-grid td { border: 0; width: 50%; text-align: center; padding-top: 34px; }
        @media print {
            body { color: #000; }
            .header { break-after: avoid; }
            table { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="header">
    <div class="logo">NC Logo</div>
    <h1>اللجنة الوطنية لتنظيم وتمويل الواردات</h1>
    <div>وثيقة تأكيد مصارفة خارجية لتمويل الاستيراد</div>
</div>

<div class="meta">
    <p><strong>رقم الوثيقة:</strong> {{ $declarationNumber }}</p>
    <p><strong>تاريخ الإصدار:</strong> {{ $issuedAt->format('Y-m-d H:i') }}</p>
    <p><strong>رقم طلب التمويل:</strong> {{ $requestModel->reference_number }}</p>
    <p><strong>الجهة المصدرة:</strong> {{ $issuer->name }}</p>
</div>

<table>
    <tr><th>البنك التجاري</th><td>{{ $requestModel->bank?->name }} ({{ $requestModel->bank?->code }})</td></tr>
    <tr><th>اسم المورد</th><td>{{ $requestModel->supplier_name }}</td></tr>
    <tr><th>المبلغ</th><td>{{ number_format((float)$requestModel->amount, 2) }} {{ $requestModel->currency }}</td></tr>
    <tr><th>وصف البضائع</th><td>{{ $requestModel->goods_description }}</td></tr>
    <tr><th>منفذ الدخول</th><td>{{ $requestModel->port_of_entry }}</td></tr>
</table>

<table>
    <tr><th>تاريخ موافقة البنك</th><td>{{ optional($requestModel->bank_approved_at)->format('Y-m-d H:i') }}</td></tr>
    <tr><th>تاريخ موافقة لجنة المساندة</th><td>{{ optional($requestModel->support_approved_at)->format('Y-m-d H:i') }}</td></tr>
    <tr><th>تاريخ القرار التنفيذي</th><td>{{ optional($requestModel->executive_decided_at)->format('Y-m-d H:i') }}</td></tr>
</table>

<div class="notice">
    بناءً على اكتمال الموافقات النظامية والتنفيذية، تصدر هذه الوثيقة كتأكيد رسمي للمصارفة الخارجية ضمن منظومة اللجنة الوطنية لتنظيم وتمويل الواردات.
</div>

<div class="signatures">
    <table class="signature-grid">
        <tr>
            <td>توقيع مدير اللجنة<br>{{ $issuer->name }}<br>____________________</td>
            <td>ختم اللجنة الوطنية<br>____________________</td>
        </tr>
    </table>
</div>
</body>
</html>

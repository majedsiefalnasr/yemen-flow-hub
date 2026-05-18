<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير نشاط البنك</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; direction: rtl; text-align: right; font-size: 12px; color: #111; line-height: 1.55; }
        .header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #222; padding-bottom: 12px; }
        h1 { margin: 4px 0; font-size: 20px; }
        .meta { font-size: 11px; color: #555; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th { background: #f0f0f0; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        .section-title { font-size: 14px; font-weight: bold; margin: 14px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير نشاط البنك</h1>
        <div class="meta">
            @if($from_date || $to_date)
                الفترة: {{ $from_date ?? 'البداية' }} — {{ $to_date ?? 'اليوم' }}
            @else
                جميع الفترات
            @endif
            &nbsp;|&nbsp; تاريخ الإصدار: {{ now()->format('Y-m-d') }}
        </div>
    </div>

    <div class="section-title">إحصاءات البنك</div>
    <table>
        <thead>
            <tr>
                <th>البنك</th>
                <th>إجمالي الطلبات</th>
                <th>المعتمدة</th>
                <th>المرفوضة</th>
                <th>المعلقة</th>
                <th>نسبة الاعتماد</th>
                <th>نسبة الرفض</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td>{{ $row['bank_name'] }}</td>
                <td>{{ $row['total_requests'] }}</td>
                <td>{{ $row['approved_count'] }}</td>
                <td>{{ $row['rejected_count'] }}</td>
                <td>{{ $row['pending_count'] }}</td>
                <td>{{ $row['approval_rate'] }}%</td>
                <td>{{ $row['rejection_rate'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

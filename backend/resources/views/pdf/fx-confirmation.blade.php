<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'ibmplexsansarabic', sans-serif;
        font-size: 11pt;
        color: #20242b;
        direction: rtl;
        margin: 0;
        padding: 0;
        line-height: 1.5;
    }
    .meta-lbl { font-weight: 600; color: #20242b; font-size: 10pt; }
    .meta-val { font-size: 10pt; color: #667085; }
    h1 { text-align: center; font-size: 17pt; font-weight: 700; margin: 18pt 0 6pt; color: #20242b; }
    .rule { border: none; border-top: 1px solid #d6d9df; width: 56pt; margin: 6pt auto 10pt; }
    .intro { text-align: center; color: #3f4651; font-size: 12pt; margin: 0 0 6pt; line-height: 1.65; }
    .recipient { text-align: center; font-size: 12pt; font-weight: 600; margin: 0 0 8pt; }
    .section-title { font-size: 11pt; font-weight: 700; margin: 9pt 0 4pt; }
    table.dt { width: 100%; border-collapse: collapse; margin-bottom: 5pt; font-size: 10.5pt; }
    table.dt th, table.dt td { border: 1px solid #d6d9df; padding: 4pt 6pt; vertical-align: top; }
    table.dt th { background-color: #f6f7f9; color: #4b5563; font-weight: 600; text-align: right; width: 25%; }
    table.dt td { font-weight: 500; text-align: right; }
    table.dt td.ltr { direction: ltr; text-align: left; }
    table.dt td.ctr { text-align: center; }
    table.dt th.ctr { text-align: center; }
    table.dt td.muted { color: #667085; font-weight: 400; }
    table.dt td.bold-val { font-weight: 700; text-align: center; }
    table.dt td.note-val { text-align: center; color: #667085; }
    .attachment-note { margin-top: 8pt; padding: 5pt 7pt; border: 1px solid #e5e7eb; font-size: 11pt; }
    .closing { margin-top: 10pt; text-align: center; font-size: 12pt; font-weight: 700; }
    table.sig { width: 100%; border-collapse: collapse; font-size: 10.5pt; margin-top: 20pt; }
    table.sig th, table.sig td { border: 1px solid #b8bec8; padding: 7pt 8pt; vertical-align: top; }
    table.sig th { background-color: #f6f7f9; font-weight: 700; color: #4b5563; text-align: center; }
    table.sig td.seal { height: 62pt; text-align: center; color: #667085; vertical-align: middle; }
    table.sig td.lines td { color: #667085; padding: 5pt 0; border: none; }
    .footer { margin-top: 8pt; padding-top: 5pt; border-top: 1px solid #d6d9df; text-align: center; color: #667085; font-size: 8.5pt; }
</style>
</head>
<body>

{{-- Header: meta on the right --}}
<table width="100%" style="border:none; border-bottom:1px solid #d6d9df; padding-bottom:5pt; margin-bottom:0;">
    <tr>
        <td style="vertical-align:top;">&nbsp;</td>
        <td style="vertical-align:top; text-align:left; white-space:nowrap;" width="1">
            <table style="border:none;">
                <tr>
                    <td style="padding:1pt 3pt;"><span class="meta-lbl">التاريخ:</span></td>
                    <td style="padding:1pt 3pt;"><span class="meta-val">{{ $date }}</span></td>
                </tr>
                <tr>
                    <td style="padding:1pt 3pt;"><span class="meta-lbl">رقم الوثيقة:</span></td>
                    <td style="padding:1pt 3pt; direction:ltr; text-align:left;"><span class="meta-val">{{ $documentNumber ?: '—' }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<h1>وثيقة تأكيد مصارفة / تغطية خارجية</h1>
<div class="rule"></div>

<div class="intro">تأكيد بيانات المصارفة أو التغطية الخارجية الخاصة بطلب الاستيراد الموضح أدناه.</div>
<div class="recipient">اللجنة الوطنية لتنظيم وتمويل الواردات - المحترمون</div>

{{-- بيانات المستورد والوثيقة --}}
<div class="section-title">بيانات المستورد والوثيقة</div>
<table class="dt">
    <tr>
        <th>اسم المستورد</th>
        <td>{{ $merchantName }}</td>
        <th>الرقم الضريبي</th>
        <td class="muted">{{ $taxNumber ?? '—' }}</td>
    </tr>
    <tr>
        <th>البنك</th>
        <td class="muted">{{ $bankName }}</td>
        <th>رقم المرجع</th>
        <td class="ltr">{{ $referenceNumber ?: '—' }}</td>
    </tr>
</table>

{{-- بيان المصارفة / التغطية --}}
<div class="section-title">بيان المصارفة / التغطية</div>
<table class="dt">
    <tr>
        <th class="ctr">البند</th>
        <th class="ctr">البيان</th>
        <th class="ctr">الإيضاحات</th>
    </tr>
    <tr>
        <td>نوع السلعة</td>
        <td class="bold-val">{{ $goodsType }}</td>
        <td class="note-val">—</td>
    </tr>
    <tr>
        <td>قيمة الفاتورة الأولية / التجارية</td>
        <td class="bold-val ltr">{{ $currency }} {{ number_format((float) $amount) }}</td>
        <td class="note-val">نسخة من الفاتورة مرفقة</td>
    </tr>
    <tr>
        <td>مبلغ وعملة المصارفة / التغطية</td>
        <td class="bold-val ltr">{{ $yerEquivalent ? 'YER '.number_format((float) $yerEquivalent) : '—' }}</td>
        <td class="note-val">—</td>
    </tr>
    <tr>
        <td>منفذ الدخول</td>
        <td class="bold-val">{{ $arrivalPort }}</td>
        <td class="note-val">—</td>
    </tr>
    <tr>
        <td>الكمية</td>
        <td class="bold-val">{{ $quantity ?? '—' }}</td>
        <td class="note-val">—</td>
    </tr>
</table>

{{-- المرفقات --}}
<div class="section-title">المرفقات</div>
<table class="dt">
    <tr>
        <th>نسخة من الفاتورة</th>
        <td>مرفق</td>
        <th>إشعار المصارفة / التغطية</th>
        <td class="muted">يضاف عند التوفر</td>
    </tr>
</table>

<div class="attachment-note">نؤكد أن البيانات الواردة أعلاه صحيحة ومطابقة للمستندات المرفقة الخاصة بعملية المصارفة / التغطية الخارجية.</div>
<div class="closing">وتقبلوا تحياتنا،،</div>

{{-- Signature area --}}
<table class="sig">
    <tr>
        <th style="width:45%;">ختم البنك / الشركة</th>
        <th>اسم وتوقيع المسؤول المختص</th>
    </tr>
    <tr>
        <td class="seal">مساحة الختم</td>
        <td class="lines">
            <table width="100%" style="border:none;">
                <tr><td>الاسم: ................................................</td></tr>
                <tr><td>الصفة: ................................................</td></tr>
                <tr><td>التوقيع: ..............................................</td></tr>
                <tr><td>التاريخ: ...... / ...... / ............</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="footer">Yemen Flow Hub — نموذج وثيقة تأكيد مصارفة / تغطية خارجية</div>

</body>
</html>

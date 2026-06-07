<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'ibmplexsansarabic', sans-serif;
        font-size: 9.5pt;
        color: #20242b;
        direction: rtl;
        margin: 0; padding: 0;
        line-height: 1.4;
    }
    .meta-lbl { font-weight: 600; color: #20242b; font-size: 9pt; }
    .meta-val { font-size: 9pt; color: #667085; }
    h1 { text-align: center; font-size: 15pt; font-weight: 700; margin: 10pt 0 4pt; color: #20242b; }
    .rule { border: none; border-top: 1px solid #d6d9df; width: 50pt; margin: 4pt auto 6pt; }
    /* Display-only notice */
    .notice { margin: 0 0 6pt; padding: 5pt 8pt; border: 1px solid #d6d9df; background-color: #fafafa; text-align: center; font-size: 8.5pt; color: #475467; line-height: 1.5; }
    .notice-title { font-size: 9.5pt; font-weight: 700; color: #20242b; }
    .recipient { text-align: center; font-size: 10pt; font-weight: 600; margin: 0 0 4pt; }
    .intro { text-align: center; color: #3f4651; font-size: 9.5pt; margin: 0 0 5pt; line-height: 1.5; }
    .section-title { font-size: 9.5pt; font-weight: 700; margin: 5pt 0 2pt; }
    table.dt { width: 100%; border-collapse: collapse; margin-bottom: 4pt; font-size: 9pt; }
    table.dt th, table.dt td { border: 1px solid #d6d9df; padding: 3pt 5pt; vertical-align: middle; }
    table.dt th { background-color: #f6f7f9; color: #4b5563; font-weight: 600; text-align: right; width: 25%; }
    table.dt td { font-weight: 500; text-align: right; }
    table.dt td.ltr { direction: ltr; text-align: left; }
    table.dt td.amt { font-size: 9.5pt; font-weight: 700; text-align: center; }
    table.dt th.ctr, table.dt td.ctr { text-align: center; }
    table.dt td.muted { color: #667085; font-weight: 400; }
    .check { width: 8pt; text-align: center; }
    table.cb-tbl { border-collapse: collapse; margin: 0 auto; }
    table.cb-tbl td {
        width: 10pt; height: 10pt;
        text-align: center; font-size: 8pt; line-height: 10pt;
        padding: 0; vertical-align: middle;
    }
    table.cb-tbl td.cb-on {
        border: 1.2px solid #6b7280;
        background-color: #6b7280;
        color: #ffffff; font-weight: 700;
    }
    table.cb-tbl td.cb-off {
        border: 1.2px solid #9ca3af;
    }
    .statement { margin-top: 5pt; padding: 3pt 6pt; border: 1px solid #e5e7eb; font-size: 9pt; }
    .closing { margin-top: 5pt; text-align: center; font-size: 10pt; font-weight: 700; }
    .preview-note { margin-top: 8pt; padding: 4pt 8pt; border: 1px solid #d6d9df; background-color: #ffffff; text-align: center; color: #475467; font-size: 8pt; line-height: 1.6; }
    .footer { margin-top: 5pt; padding-top: 4pt; border-top: 1px solid #d6d9df; text-align: center; color: #667085; font-size: 7.5pt; line-height: 1.6; }
    .footer-brand { font-weight: 600; color: #4b5563; }
</style>
</head>
<body>

{{-- Header --}}
<table width="100%" style="border:none; border-bottom:1px solid #d6d9df; padding-bottom:4pt; margin-bottom:0;">
    <tr>
        <td style="vertical-align:top;">&nbsp;</td>
        <td style="vertical-align:top; text-align:left; white-space:nowrap;" width="1">
            <table style="border:none;">
                <tr>
                    <td style="padding:1pt 2pt;"><span class="meta-lbl">التاريخ:</span></td>
                    <td style="padding:1pt 2pt;"><span class="meta-val">{{ $date }}</span></td>
                </tr>
                <tr>
                    <td style="padding:1pt 2pt;"><span class="meta-lbl">رقم الوثيقة:</span></td>
                    <td style="padding:1pt 2pt; direction:ltr; text-align:left;"><span class="meta-val">{{ $documentNumber }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<h1>طلب وثيقة تأكيد مصارفة خارجية</h1>
<div class="rule"></div>

{{-- Display-only notice --}}
<div class="notice">
    <span class="notice-title">نسخة إلكترونية للعرض - </span>هذه الوثيقة معروضة لأغراض المتابعة والاستعراض داخل المنظومة الإلكترونية فقط، ولا تمثل وثيقة رسمية أو معتمدة. تعتمد النسخة الرسمية حصراً بعد استكمال إجراءات المراجعة والاعتماد وفقاً للضوابط المعمول بها.
</div>

{{-- بيانات البنك والمستورد --}}
<div class="section-title">بيانات البنك والمستورد</div>
<table class="dt">
    <tr>
        <th>البنك</th><td>{{ $bankName }}</td>
        <th>اسم المستورد</th><td>{{ $merchantName }}</td>
    </tr>
    <tr>
        <th>رقم السجل التجاري</th><td class="ltr">{{ $commercialRegNo }}</td>
        <th>رقم مرجع موافقة اللجنة</th><td class="muted">{{ $committeeApprovalNo ?? '—' }}</td>
    </tr>
</table>

{{-- بيانات المورد والفاتورة --}}
<div class="section-title">بيانات المورد والفاتورة</div>
<table class="dt">
    <tr>
        <th>اسم المورد</th><td>{{ $supplierName }}</td>
        <th>بلد المنشأ</th><td>{{ $originCountry }}</td>
    </tr>
    <tr>
        <th>رقم الفاتورة</th><td class="ltr">{{ $invoiceNumber }}</td>
        <th>تاريخ الفاتورة</th><td class="ltr">{{ $invoiceDate }}</td>
    </tr>
</table>

{{-- بيانات التمويل والاستيراد --}}
<div class="section-title">بيانات التمويل والاستيراد</div>
<table class="dt">
    <tr>
        <th class="ctr">مبلغ التمويل</th>
        <th class="ctr">العملة</th>
        <th class="ctr">نوع الواردات</th>
        <th class="ctr">شروط الدفع</th>
        <th class="ctr">تاريخ الاستحقاق</th>
    </tr>
    <tr>
        <td class="amt ltr">{{ number_format((float) $amount) }}</td>
        <td class="amt ltr ctr">{{ $currency }}</td>
        <td class="ctr">{{ $goodsType }}</td>
        <td class="ctr">{{ $paymentTerms }}</td>
        <td class="ctr ltr">{{ $dueDate }}</td>
    </tr>
    <tr>
        <th>وصف البضاعة</th>
        <td colspan="4" style="text-align:right;">{{ $goodsDescription }}</td>
    </tr>
</table>

{{-- بيانات الشحن ومنفذ الدخول --}}
<div class="section-title">بيانات الشحن ومنفذ الدخول</div>
<table class="dt">
    <tr>
        <th>ميناء الوصول / منفذ الدخول</th><td>{{ $arrivalPort }}</td>
        <th>ميناء الشحن</th><td>{{ $shippingPort }}</td>
    </tr>
    <tr>
        <th>الجمارك المختصة</th><td>{{ $customsOffice }}</td>
        <th>رقم بوليصة الشحن</th><td class="ltr">{{ $blNumber }}</td>
    </tr>
</table>

{{-- المستندات المرفقة — reflects the request's actual attached documents --}}
<div class="section-title">المستندات المرفقة</div>
@php
    $docPairs = array_chunk($attachedDocs, 2);
@endphp
<table class="dt">
    <tr>
        <th>المستند</th>
        <th class="check ctr">مرفق</th>
        <th>المستند</th>
        <th class="check ctr">مرفق</th>
    </tr>
    @foreach ($docPairs as $pair)
        <tr>
            @foreach ($pair as $doc)
                <td>{{ $doc['label'] }}</td>
                <td class="check ctr">
                    <table class="cb-tbl"><tr><td class="{{ $doc['attached'] ? 'cb-on' : 'cb-off' }}">{{ $doc['attached'] ? '✓' : '' }}</td></tr></table>
                </td>
            @endforeach
            @if (count($pair) === 1)
                <td class="muted">&mdash;</td>
                <td class="check ctr"></td>
            @endif
        </tr>
    @endforeach
</table>

<div class="statement">نتحمل مسؤولية صحة البيانات المدونة أعلاه.</div>
<div class="closing">وتفضلوا بقبول فائق التقدير والاحترام</div>

<div class="preview-note">لا تحتوي هذه النسخة على ختم أو توقيع، ولا يجوز استخدامها لأي أغراض تنظيمية أو قانونية أو تنفيذية.</div>

<div class="footer">
    <div class="footer-brand">نسخة إلكترونية للعرض والمتابعة داخل منظومة اللجنة الوطنية لتنظيم وتمويل الواردات</div>
    <div>لا تعتبر هذه النسخة وثيقة رسمية أو معتمدة، ولا يجوز استخدامها خارج المنظومة أو الاستناد إليها في أي إجراء تنظيمي أو قانوني.</div>
</div>

</body>
</html>

# Implementation Plan: PDF Document Generation
## طلب وثيقة التأكيد + وثيقة تأكيد المصارفة الخارجية

---

## Overview

Two official government documents must be generated as pre-filled PDFs by the system, downloaded by the responsible role, physically stamped and signed offline, then re-uploaded as mandatory evidence before the workflow can advance.

| # | Document (Arabic) | Direction | Who generates | Who stamps | When |
|---|---|---|---|---|---|
| 1 | **طلب وثيقة التأكيد** | Bank → NCRFI | System (pre-fill from request data) | DATA_ENTRY downloads → bank stamps → re-uploads | Wizard Step 3 (before submit) |
| 2 | **وثيقة تأكيد مصارفة / تغطية خارجية** | NCRFI → Bank | System (pre-fill from request data) | COMMITTEE_DIRECTOR downloads → CBY stamps + director signs → re-uploads | Request detail page at `EXECUTIVE_APPROVED` |

Both PDFs must look like authentic banking documents: official letterhead, CBY/NCRFI brand colors (`#0066cc`), the Yemen national emblem (already at `frontend/public/brand/yemen-emblem.svg`), clean bilingual Arabic-English tables, A4 portrait, RTL-primary.

---

## Document 1 Content Reference

From the physical template `نموذج طلب وثيقة تاكيد.pdf`:

```
Date: [submission date]

الإخوة / اللجنة الوطنية لتنظيم وتمويل الواردات    المحترمون

الموضوع / طلب وثيقة تأكيد للجمارك
(underlined, bold)

[body paragraph — fixed text]

┌──────────────────────────────────────────────────┐
│ أسم التاجر المستورد    │ [merchant.name]         │
├──────────────────────────────────────────────────┤
│ اسم النشاط التجاري    │ [merchant.business_type] │
├──────────────────────────────────────────────────┤
│ رقم موافقة اللجنة     │ (left blank)             │
├────────────────────────┬────────────┬────────────┤
│ نوع الفاتورة           │ مبلغ       │ [amount]   │
│                        │ قيمة/عملة  │ [cur+amt]  │
│                        │ السداد     │ [payment]  │
├────────────────────────┴────────────┴────────────┤
│ نوع السلعة: [goods_type]                         │
│ منفذ الدخول: [arrival_port]  الكمية: [qty or —] │
├──────────────────────────────────────────────────┤
│ وثائق التاجر (checkboxes):                       │
│ ☑/☐ صورة البطاقة الضريبية                       │
│ ☑/☐ صورة الفاتورة (نسخة واضحة)                 │
│ ☐   اشعار المصارفة أو الايداع                   │
│ ☐   صورة سويفت التحويل                          │
│ ☐   سويفت الخصم الخارجي                         │
└──────────────────────────────────────────────────┘

- نتحمل مسئولية صحة البيانات المدونة أعلاه.

وتفضلوا بقبول فائق التقدير والاحترام

ختم البنك/الشركة         [bank.name]
```

Checkboxes for البطاقة الضريبية and صورة الفاتورة are ticked if those files were already uploaded in the wizard session (tracked via the request's linked documents). All others remain unticked blank squares.

---

## Document 2 Content Reference

From the physical template `نموذج وثيقة تأكيد مصارفة-تغطية خارجية.pdf`:

```
[Yemen emblem / NCRFI logo — top right circle]

وثيقة تأكيد مصارفة/تغطية خارجية     تاريخ / [date]
(bold, underlined, large)

اسم التاجر/المستورد: [merchant.name]    رقم الوثيقة: [doc_number]
الرقم الضريبي: [merchant.tax_number]

┌─────────────────────────────────┬─────────────────────────┬──────────┐
│ البند                           │ البيان                  │ إضاحات  │
├─────────────────────────────────┼─────────────────────────┼──────────┤
│ نوع السلعة                     │ [goods_type]             │          │
├─────────────────────────────────┼─────────────────────────┼──────────┤
│ قيمة الفاتورة الأولية/التجارية │ [currency] [amount]      │          │
├─────────────────────────────────┼─────────────────────────┼──────────┤
│ مبلغ وعملة المصارفة/التغطية   │ YER [yer_equivalent]     │          │
├─────────────────────────────────┼─────────────────────────┼──────────┤
│ منفذ الدخول                    │ [arrival_port]           │          │
├─────────────────────────────────┼─────────────────────────┼──────────┤
│ الكمية                         │ [quantity or —]          │          │
└─────────────────────────────────┴─────────────────────────┴──────────┘

مرفق نسخة من الفاتورة

وتقبلوا تحياتنا،،،


اللجنة الوطنية لتنظيم وتمويل الواردات
[director name]
_________________________
```

---

## Workflow Changes Required

### New status: `FX_CONFIRMATION_PENDING`

The current `RequestStatus` enum in `backend/app/Enums/RequestStatus.php` is **missing** `FX_CONFIRMATION_PENDING`. It must be added.

The full corrected path for the Director stage is:
```
EXECUTIVE_APPROVED
  → (director uploads signed doc) → FX_CONFIRMATION_PENDING
  → (director clicks issue)       → CUSTOMS_DECLARATION_ISSUED
  → (system auto-completes)       → COMPLETED
```

### New DocumentType: `CONFIRMATION_REQUEST`

The current `DocumentType` enum has: `REQUEST_DOC`, `SWIFT`, `FX_REQUEST`, `CUSTOMS`.
Add: `CONFIRMATION_REQUEST` = `'CONFIRMATION_REQUEST'` with label `'طلب وثيقة التأكيد / Confirmation Request'`.

This is the document type used when DATA_ENTRY uploads the stamped Document 1 in the wizard.

The existing `FX_REQUEST` type is used for the signed Document 2 uploaded by COMMITTEE_DIRECTOR.

---

## Affected Files

### Backend (new)
```
backend/app/Services/Documents/PdfGeneratorService.php
backend/resources/views/pdf/confirmation-request.blade.php
backend/resources/views/pdf/fx-confirmation.blade.php
backend/resources/fonts/                          ← Arabic font files
backend/public/brand/yemen-emblem.png             ← PNG copy of SVG (see §2)
backend/app/Http/Controllers/Api/DocumentTemplateController.php
backend/app/Http/Requests/FxConfirmationUploadRequest.php
backend/database/migrations/[timestamp]_add_fx_confirmation_pending_to_request_status.php
backend/database/migrations/[timestamp]_add_confirmation_request_to_document_types.php
backend/database/migrations/[timestamp]_add_signed_fx_doc_to_customs_declarations.php
backend/database/migrations/[timestamp]_add_yer_equivalent_qty_to_import_requests.php
```

### Backend (modified)
```
backend/app/Enums/RequestStatus.php               ← add FX_CONFIRMATION_PENDING
backend/app/Enums/DocumentType.php                ← add CONFIRMATION_REQUEST
backend/app/Enums/WorkflowAction.php              ← add UPLOAD_FX_CONFIRMATION
backend/app/Enums/AuditAction.php                 ← add FX_CONFIRMATION_UPLOADED, FX_CONFIRMATION_ISSUED
backend/app/Services/Workflow/WorkflowService.php ← add FX_CONFIRMATION_PENDING transition
backend/app/Services/Customs/CustomsService.php   ← require signed_fx_doc before issuing
backend/app/Models/ImportRequest.php              ← add yer_equivalent, quantity fillable
backend/app/Models/CustomsDeclaration.php         ← add signed_fx_doc_path fillable
backend/routes/api.php                            ← new endpoints
```

### Frontend (new)
```
frontend/app/components/requests/FxConfirmationCard.vue
```

### Frontend (modified)
```
frontend/app/composables/useRequestWizard.ts      ← add confirmation_request doc key
frontend/app/components/wizard/WizardStep3.vue    ← download card + new upload zone
frontend/app/components/wizard/RequestWizard.vue  ← auto-save draft on step 3 entry
frontend/app/composables/useRequests.ts           ← new API calls
frontend/app/stores/requests.store.ts             ← new actions
frontend/app/pages/requests/[id]/index.vue        ← mount FxConfirmationCard
frontend/app/types/enums.ts                       ← add FX_CONFIRMATION_PENDING
```

---

## Section 1 — Package Installation (mPDF)

### Why mPDF over the existing DomPDF

The existing `barryvdh/laravel-dompdf` does **not** implement the Unicode Bidirectional Algorithm or OpenType Arabic shaping. Arabic letters appear disconnected in DomPDF output (ligatures missing, letters not joined). mPDF ships with full OpenType Layout (OTL) support, which correctly shapes Arabic text — letters connect, harakat render, kashida extends. DomPDF remains installed for the legacy customs preview endpoint but all new PDF generation uses mPDF.

### Install

```bash
composer require mpdf/mpdf
```

No Laravel service provider needed — instantiate directly in `PdfGeneratorService`.

### Arabic font setup

Place the following font files in `backend/resources/fonts/`:
- `Amiri-Regular.ttf` — Arabic body text (download from https://github.com/aliftype/amiri/releases)
- `Amiri-Bold.ttf`

mPDF font configuration is passed inline when constructing the `Mpdf` instance (see `PdfGeneratorService` below). No `config/mpdf.php` file needed.

---

## Section 2 — Brand Assets

### Yemen emblem PNG

The vector SVG at `frontend/public/brand/yemen-emblem.svg` must be exported as PNG for mPDF (mPDF's SVG support is incomplete for complex paths).

Create `backend/public/brand/yemen-emblem.png` at **128 × 128 px** with a transparent background. If no tooling is available during development, use any placeholder PNG at that path and replace with the real export before QA.

Reference in Blade templates as: `public_path('brand/yemen-emblem.png')`.

### Brand colors used in PDFs

| Usage | Value |
|---|---|
| Table header background | `#0066cc` |
| Table header text | `#ffffff` |
| Label cell background | `#e8f0fb` |
| Body text | `#1c222b` |
| Border | `#cccccc` |
| Subtle text (footer) | `#6c757d` |
| Page background | `#ffffff` |

---

## Section 3 — Backend Changes

### 3.1 Enum: RequestStatus

File: `backend/app/Enums/RequestStatus.php`

Add immediately after `case EXECUTIVE_APPROVED`:
```php
case FX_CONFIRMATION_PENDING = 'FX_CONFIRMATION_PENDING';
```

Add to `label()`:
```php
self::FX_CONFIRMATION_PENDING => 'بانتظار إصدار المصارفة الخارجية / FX Confirmation Pending',
```

Add `FX_CONFIRMATION_PENDING` to the `isTerminal()` exclusion list (it is NOT terminal).

### 3.2 Enum: DocumentType

File: `backend/app/Enums/DocumentType.php`

Add:
```php
case CONFIRMATION_REQUEST = 'CONFIRMATION_REQUEST';
```

Add to `label()`:
```php
self::CONFIRMATION_REQUEST => 'طلب وثيقة التأكيد / Confirmation Request',
```

### 3.3 Enum: WorkflowAction

File: `backend/app/Enums/WorkflowAction.php`

Add:
```php
case UPLOAD_FX_CONFIRMATION = 'UPLOAD_FX_CONFIRMATION';
```

The existing `ISSUE_CUSTOMS` and `COMPLETE` actions are reused for the subsequent transitions.

### 3.4 Enum: AuditAction

File: `backend/app/Enums/AuditAction.php`

Add:
```php
case FX_CONFIRMATION_UPLOADED = 'FX_CONFIRMATION_UPLOADED';
case FX_CONFIRMATION_ISSUED   = 'FX_CONFIRMATION_ISSUED';
```

Add to `label()`:
```php
self::FX_CONFIRMATION_UPLOADED => 'FX Confirmation Uploaded / رفع وثيقة المصارفة الخارجية',
self::FX_CONFIRMATION_ISSUED   => 'FX Confirmation Issued / إصدار وثيقة المصارفة الخارجية',
```

### 3.5 Migrations

#### Migration A — Add FX_CONFIRMATION_PENDING to import_requests status ENUM

```php
// filename: [timestamp]_add_fx_confirmation_pending_to_request_status.php
public function up(): void
{
    if (DB::getDriverName() === 'mysql') {
        DB::statement("ALTER TABLE import_requests MODIFY COLUMN current_status ENUM(
            'DRAFT','DRAFT_REJECTED_INTERNAL','SUBMITTED','BANK_REVIEW',
            'BANK_APPROVED','SUPPORT_REVIEW_PENDING','SUPPORT_REVIEW_IN_PROGRESS',
            'SUPPORT_APPROVED','SUPPORT_REJECTED','WAITING_FOR_SWIFT','SWIFT_UPLOADED',
            'WAITING_FOR_VOTING_OPEN','EXECUTIVE_VOTING_OPEN','EXECUTIVE_VOTING_CLOSED',
            'EXECUTIVE_APPROVED','EXECUTIVE_REJECTED','FX_CONFIRMATION_PENDING',
            'CUSTOMS_DECLARATION_ISSUED','COMPLETED',
            'BANK_RETURNED','SUPPORT_RETURNED','BANK_REJECTED'
        ) NOT NULL DEFAULT 'DRAFT'");
    }
}
```

#### Migration B — Add CONFIRMATION_REQUEST to request_documents type ENUM

```php
// filename: [timestamp]_add_confirmation_request_to_document_types.php
public function up(): void
{
    if (DB::getDriverName() === 'mysql') {
        DB::statement("ALTER TABLE request_documents MODIFY COLUMN type
            ENUM('REQUEST_DOC','SWIFT','FX_REQUEST','CUSTOMS','CONFIRMATION_REQUEST') NOT NULL");
    }
}
```

#### Migration C — Add signed_fx_doc columns to customs_declarations

```php
// filename: [timestamp]_add_signed_fx_doc_to_customs_declarations.php
public function up(): void
{
    Schema::table('customs_declarations', function (Blueprint $table) {
        $table->string('signed_fx_doc_path')->nullable()->after('pdf_path');
        $table->timestamp('signed_fx_doc_uploaded_at')->nullable()->after('signed_fx_doc_path');
        $table->foreignId('signed_fx_doc_uploaded_by')
              ->nullable()->after('signed_fx_doc_uploaded_at')
              ->constrained('users')->nullOnDelete();
    });
}
```

#### Migration D — Add yer_equivalent and quantity to import_requests

These fields are needed for Document 2. They are nullable — existing requests without them show `—` in the template.

```php
// filename: [timestamp]_add_yer_equivalent_qty_to_import_requests.php
public function up(): void
{
    Schema::table('import_requests', function (Blueprint $table) {
        $table->decimal('yer_equivalent', 18, 2)->nullable()->after('amount');
        $table->string('quantity')->nullable()->after('yer_equivalent');
        // quantity is a free-text string (e.g. "50 حاوية", "200 طن")
    });
}
```

> **Note:** `quantity` and `yer_equivalent` are not currently part of the wizard form. They can be added to Step 1 of the wizard in a separate story. For this story, the fields exist in the DB and appear as `—` in generated PDFs if null. The wizard's `saveDraft` / `submitRequest` API call already passes all Step1 fields through, so adding the fields to the wizard form is a pure frontend addition that does not block this story.

### 3.6 Model Updates

#### ImportRequest model

Add to `$fillable`:
```php
'yer_equivalent',
'quantity',
```

#### CustomsDeclaration model

Add to `$fillable`:
```php
'signed_fx_doc_path',
'signed_fx_doc_uploaded_at',
'signed_fx_doc_uploaded_by',
```

Add cast:
```php
'signed_fx_doc_uploaded_at' => 'datetime',
```

Add accessor `hasSigned()`:
```php
public function hasSigned(): bool
{
    return $this->signed_fx_doc_path !== null;
}
```

### 3.7 PdfGeneratorService

File: `backend/app/Services/Documents/PdfGeneratorService.php`

This service is the single entry point for all mPDF generation in the project.

```php
<?php

namespace App\Services\Documents;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class PdfGeneratorService
{
    private function makeMpdf(): Mpdf
    {
        $defaultConfig   = (new ConfigVariables())->getDefaults();
        $defaultFontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $defaultFontData   = $defaultFontConfig['fontdata'];

        return new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'default_font'      => 'amiri',
            'default_font_size' => 11,
            'margin_top'        => 20,
            'margin_right'      => 20,
            'margin_bottom'     => 20,
            'margin_left'       => 20,
            'directionality'    => 'rtl',
            'fontDir'           => array_merge($defaultFontDirs, [
                resource_path('fonts'),
            ]),
            'fontdata'          => $defaultFontData + [
                'amiri' => [
                    'R'  => 'Amiri-Regular.ttf',
                    'B'  => 'Amiri-Bold.ttf',
                    'useOTL'    => 0xFF,
                    'useKashida' => 75,
                ],
            ],
        ]);
    }

    /**
     * Generate a PDF from a Blade view and return raw PDF bytes.
     * $data is passed as-is to the Blade template.
     */
    public function generate(string $view, array $data): string
    {
        $html = view($view, $data)->render();

        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * Stream a PDF directly to the browser as a download.
     */
    public function download(string $filename, string $view, array $data): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pdfBytes = $this->generate($view, $data);

        return response()->streamDownload(
            function () use ($pdfBytes) { echo $pdfBytes; },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
```

Bind in `AppServiceProvider::register()`:
```php
$this->app->singleton(\App\Services\Documents\PdfGeneratorService::class);
```

### 3.8 Blade Template — Document 1: confirmation-request

File: `backend/resources/views/pdf/confirmation-request.blade.php`

Variables passed from controller:
- `$date` — formatted date string (e.g. `01/06/2026`)
- `$merchantName` — string
- `$businessActivity` — string (merchant business type)
- `$invoiceAmount` — numeric (formatted with number_format)
- `$amount` — numeric (the requested FX amount)
- `$currency` — string (USD / EUR / SAR)
- `$paymentTerms` — string
- `$goodsType` — string
- `$arrivalPort` — string
- `$quantity` — string|null
- `$bankName` — string
- `$hasTaxCard` — bool (true if tax_card doc already uploaded)
- `$hasProformaInvoice` — bool (true if proforma_invoice doc already uploaded)

**Important mPDF CSS notes:**
- No flexbox/grid — use `<table>` for all layout
- Use inline styles or `<style>` block in `<head>`
- `direction: rtl` on `<html>` tag
- Background color on `<td>` cells works correctly
- Images must use absolute file path (`src="{{ $logoPath }}"`)
- Checkboxes: render as bordered `<span>` elements, not `<input>` — mPDF ignores form inputs

```html
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }

  body {
    font-family: 'amiri', serif;
    font-size: 11pt;
    color: #1c222b;
    direction: rtl;
    margin: 0;
    padding: 0;
    line-height: 1.7;
  }

  .page { width: 100%; }

  /* ── Header / Date ───────────────────────────── */
  .date-line {
    text-align: left;
    font-size: 10pt;
    color: #6c757d;
    margin-bottom: 14mm;
  }

  .org-name {
    font-weight: bold;
    font-size: 12.5pt;
    color: #0066cc;
  }

  .recipient {
    font-size: 11pt;
    text-align: left;
  }

  /* ── Subject ─────────────────────────────────── */
  .subject {
    font-weight: bold;
    font-size: 13pt;
    text-decoration: underline;
    margin: 8mm 0 6mm 0;
    color: #1c222b;
  }

  /* ── Body paragraph ──────────────────────────── */
  .body-text {
    font-size: 11pt;
    margin-bottom: 6mm;
    text-align: justify;
  }

  /* ── Data table ──────────────────────────────── */
  table.data {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8mm;
    font-size: 11pt;
  }

  table.data td {
    border: 1px solid #cccccc;
    padding: 5pt 8pt;
    vertical-align: middle;
  }

  table.data td.lbl {
    background-color: #e8f0fb;
    font-weight: bold;
    width: 32%;
    text-align: right;
  }

  table.data td.val {
    text-align: right;
  }

  /* Inner sub-table (invoice row) */
  table.inner {
    width: 100%;
    border-collapse: collapse;
    font-size: 10.5pt;
  }

  table.inner td {
    border: none;
    padding: 2pt 4pt;
    vertical-align: middle;
  }

  table.inner td.sub-lbl {
    font-weight: bold;
    width: 30%;
    text-align: right;
    color: #444;
  }

  table.inner td.sub-val {
    text-align: right;
  }

  /* ── Checkbox ────────────────────────────────── */
  .cb-row { margin: 3pt 0; font-size: 10.5pt; }

  .cb-box {
    display: inline-block;
    width: 10pt;
    height: 10pt;
    border: 1.2px solid #1c222b;
    vertical-align: middle;
    margin-left: 5pt;
  }

  .cb-checked {
    background-color: #0066cc;
    border-color: #0066cc;
  }

  /* ── Footer ──────────────────────────────────── */
  .closing {
    font-weight: bold;
    font-size: 13pt;
    margin-top: 10mm;
  }

  .stamp-label {
    font-weight: bold;
    font-size: 13pt;
    margin-top: 12mm;
  }

  .bank-name {
    font-size: 11pt;
    margin-top: 4mm;
    color: #1c222b;
  }

  /* ── Divider ─────────────────────────────────── */
  .divider {
    border: none;
    border-top: 1px solid #cccccc;
    margin: 6mm 0;
  }
</style>
</head>
<body>
<div class="page">

  {{-- Date line (LTR because it contains Latin numerals) --}}
  <div class="date-line">Date: {{ $date }}</div>

  {{-- Addressee row --}}
  <table width="100%" style="margin-bottom:10mm; border:none;">
    <tr>
      <td style="text-align:right; width:70%;">
        <div class="org-name">الإخوة/ اللجنة الوطنية لتنظيم وتمويل الواردات</div>
      </td>
      <td style="text-align:left; width:30%;">
        <span class="recipient">المحترمون</span>
      </td>
    </tr>
  </table>

  {{-- Subject --}}
  <div class="subject">الموضوع/ طلب وثيقة تأكيد للجمارك</div>

  {{-- Body paragraph --}}
  <div class="body-text">
    بالإشارة إلى الموضوع أعلاه وبناءً على الآلية المقرة من قِبَلكم، تجدون مرفقًا ادناه التفاصيل
    الخاصة بطلب استيراد من الخارج، بحسب البيانات الموضحة أدناه:
  </div>

  {{-- Data table --}}
  <table class="data">
    <tr>
      <td class="lbl">أسم التاجر المستورد</td>
      <td class="val">{{ $merchantName }}</td>
    </tr>
    <tr>
      <td class="lbl">اسم النشاط التجاري</td>
      <td class="val">{{ $businessActivity }}</td>
    </tr>
    <tr>
      <td class="lbl">رقم موافقة اللجنة</td>
      <td class="val"></td>
    </tr>
    <tr>
      <td class="lbl">نوع الفاتورة</td>
      <td class="val">
        <table class="inner">
          <tr>
            <td class="sub-lbl">مبلغ الفاتورة</td>
            <td class="sub-val">{{ number_format($invoiceAmount) }}</td>
            <td class="sub-lbl">قيمة و عملة</td>
            <td class="sub-val">{{ $currency }} {{ number_format($amount) }}</td>
          </tr>
          <tr>
            <td class="sub-lbl">السداد</td>
            <td class="sub-val" colspan="3">{{ $paymentTerms }}</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td class="lbl">نوع السلعة</td>
      <td class="val">
        {{ $goodsType }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>منفذ الدخول:</strong> {{ $arrivalPort }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>الكمية:</strong> {{ $quantity ?? '—' }}
      </td>
    </tr>
    <tr>
      <td class="lbl">وثائق التاجر</td>
      <td class="val">
        <div class="cb-row">
          <span class="cb-box {{ $hasTaxCard ? 'cb-checked' : '' }}"></span>
          صورة البطاقة الضريبية
        </div>
        <div class="cb-row">
          <span class="cb-box {{ $hasProformaInvoice ? 'cb-checked' : '' }}"></span>
          صورة الفاتورة (نسخة واضحة)
        </div>
        <div class="cb-row">
          <span class="cb-box"></span> اشعار المصارفة أو الايداع
        </div>
        <div class="cb-row">
          <span class="cb-box"></span> صورة سويفت التحويل
        </div>
        <div class="cb-row">
          <span class="cb-box"></span> سويفت الخصم الخارجي
        </div>
      </td>
    </tr>
  </table>

  {{-- Responsibility statement --}}
  <div class="body-text">- نتحمل مسئولية صحة البيانات المدونة أعلاه.</div>

  <hr class="divider">

  {{-- Closing --}}
  <div class="closing">وتفضلوا بقبول فائق التقدير والاحترام</div>

  {{-- Stamp area --}}
  <div class="stamp-label">ختم البنك/الشركة</div>
  <div class="bank-name">{{ $bankName }}</div>

</div>
</body>
</html>
```

### 3.9 Blade Template — Document 2: fx-confirmation

File: `backend/resources/views/pdf/fx-confirmation.blade.php`

Variables passed from controller:
- `$date` — formatted date string
- `$merchantName` — string
- `$taxNumber` — string|null
- `$documentNumber` — string (e.g. `CR-2026-000001`) or empty string for template download
- `$goodsType` — string
- `$currency` — string
- `$amount` — numeric
- `$yerEquivalent` — numeric|null
- `$arrivalPort` — string
- `$quantity` — string|null
- `$directorName` — string (issuer name)
- `$logoPath` — absolute path to PNG (from `public_path('brand/yemen-emblem.png')`)

```html
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }

  body {
    font-family: 'amiri', serif;
    font-size: 11pt;
    color: #1c222b;
    direction: rtl;
    margin: 0; padding: 0;
    line-height: 1.7;
  }

  .page { width: 100%; }

  /* ── Hero header ─────────────────────────────── */
  .doc-title {
    font-weight: bold;
    font-size: 15pt;
    text-decoration: underline;
    color: #1c222b;
    vertical-align: middle;
  }

  .date-cell {
    font-size: 11pt;
    text-align: left;
    vertical-align: middle;
    color: #1c222b;
  }

  /* ── Meta row ────────────────────────────────── */
  .meta-lbl { font-weight: bold; font-size: 11pt; }
  .meta-val { font-size: 11pt; }

  /* ── Data table ──────────────────────────────── */
  table.data {
    width: 100%;
    border-collapse: collapse;
    margin: 8mm 0;
    font-size: 11pt;
  }

  table.data thead td {
    background-color: #0066cc;
    color: #ffffff;
    font-weight: bold;
    padding: 6pt 10pt;
    border: 1px solid #0066cc;
    text-align: center;
  }

  table.data tbody td {
    border: 1px solid #cccccc;
    padding: 6pt 10pt;
    vertical-align: middle;
  }

  table.data tbody td.row-lbl {
    background-color: #e8f0fb;
    font-weight: bold;
    text-align: right;
    width: 30%;
  }

  table.data tbody td.row-val {
    text-align: center;
    font-weight: bold;
    width: 45%;
  }

  table.data tbody td.row-note {
    text-align: center;
    color: #6c757d;
    width: 25%;
  }

  /* ── Footer ──────────────────────────────────── */
  .footer-note {
    font-size: 11pt;
    margin-top: 6mm;
  }

  .closing {
    font-weight: bold;
    font-size: 12pt;
    text-decoration: underline;
    margin-top: 8mm;
  }

  .issuer-name {
    font-weight: bold;
    font-size: 13pt;
    margin-top: 14mm;
  }

  .director-name {
    font-size: 11pt;
    color: #6c757d;
    margin-top: 4mm;
  }

  .sig-line {
    margin-top: 16mm;
    border-top: 1px solid #1c222b;
    width: 130px;
  }

  .divider {
    border: none;
    border-top: 1px solid #cccccc;
    margin: 6mm 0;
  }
</style>
</head>
<body>
<div class="page">

  {{-- Hero: title + logo --}}
  <table width="100%" style="border:none; margin-bottom:8mm;">
    <tr>
      <td style="text-align:right; vertical-align:middle; width:75%;">
        <span class="doc-title">وثيقة تأكيد مصارفة/تغطية خارجية</span>
      </td>
      <td style="text-align:left; vertical-align:middle; width:25%;">
        <img src="{{ $logoPath }}" width="72" height="72" alt="NCRFI" />
      </td>
    </tr>
  </table>

  {{-- Date --}}
  <div class="date-cell" style="margin-bottom:8mm;">تاريخ &nbsp; {{ $date }}</div>

  {{-- Meta block --}}
  <table width="100%" style="border:none; margin-bottom:6mm;">
    <tr>
      <td style="width:60%;">
        <span class="meta-lbl">اسم التاجر/المستورد: </span>
        <span class="meta-val">{{ $merchantName }}</span>
      </td>
      <td style="width:40%; text-align:left;">
        <span class="meta-lbl">رقم الوثيقة: </span>
        <span class="meta-val">{{ $documentNumber ?: '—' }}</span>
      </td>
    </tr>
    <tr>
      <td colspan="2">
        <span class="meta-lbl">الرقم الضريبي: </span>
        <span class="meta-val">{{ $taxNumber ?? '—' }}</span>
      </td>
    </tr>
  </table>

  <hr class="divider">

  {{-- Main data table --}}
  <table class="data">
    <thead>
      <tr>
        <td>البند</td>
        <td>البيان</td>
        <td>إضاحات</td>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="row-lbl">نوع السلعة</td>
        <td class="row-val">{{ $goodsType }}</td>
        <td class="row-note"></td>
      </tr>
      <tr>
        <td class="row-lbl">قيمة الفاتورة الأولية / التجارية</td>
        <td class="row-val">{{ $currency }} {{ number_format($amount) }}</td>
        <td class="row-note"></td>
      </tr>
      <tr>
        <td class="row-lbl">مبلغ وعملة المصارفة/التغطية</td>
        <td class="row-val">
          @if($yerEquivalent)
            YER {{ number_format($yerEquivalent) }}
          @else
            —
          @endif
        </td>
        <td class="row-note"></td>
      </tr>
      <tr>
        <td class="row-lbl">منفذ الدخول</td>
        <td class="row-val">{{ $arrivalPort }}</td>
        <td class="row-note"></td>
      </tr>
      <tr>
        <td class="row-lbl">الكمية</td>
        <td class="row-val">{{ $quantity ?? '—' }}</td>
        <td class="row-note"></td>
      </tr>
    </tbody>
  </table>

  {{-- Footer text --}}
  <div class="footer-note">مرفق نسخة من الفاتورة</div>
  <div class="closing">وتقبلوا تحياتنا،،،</div>

  {{-- Issuer block --}}
  <div class="issuer-name">اللجنة الوطنية لتنظيم وتمويل الواردات</div>
  <div class="director-name">{{ $directorName }}</div>
  <div class="sig-line"></div>

</div>
</body>
</html>
```

### 3.10 DocumentTemplateController

File: `backend/app/Http/Controllers/Api/DocumentTemplateController.php`

This controller handles two GET endpoints: template download for Document 1 and Document 2. Both return a streamed PDF response.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Services\Documents\PdfGeneratorService;
use Illuminate\Auth\Access\AuthorizationException;

class DocumentTemplateController extends Controller
{
    public function __construct(private readonly PdfGeneratorService $pdf) {}

    /**
     * GET /api/requests/{importRequest}/confirmation-request-template
     *
     * Generates a pre-filled Document 1 PDF for DATA_ENTRY to download, stamp, and re-upload.
     * Request must be in DRAFT or BANK_RETURNED or SUPPORT_RETURNED status.
     * Accessible to: DATA_ENTRY and BANK_ADMIN of the same bank.
     */
    public function confirmationRequest(ImportRequest $importRequest)
    {
        $user = request()->user();

        $allowed = match ($user->role) {
            UserRole::DATA_ENTRY, UserRole::BANK_ADMIN =>
                $user->bank_id !== null && $user->bank_id === $importRequest->bank_id,
            default => false,
        };

        if (!$allowed) {
            throw new AuthorizationException();
        }

        $importRequest->load(['merchant', 'bank', 'documents']);

        $hasTaxCard        = $importRequest->documents
            ->where('type', DocumentType::REQUEST_DOC)
            ->contains(fn($d) => str_contains(strtolower($d->original_name ?? ''), 'tax'));
        $hasProformaInvoice = $importRequest->documents
            ->where('type', DocumentType::REQUEST_DOC)
            ->isNotEmpty();
        // Prefer a dedicated sub-type in a future refactor; for now: any REQUEST_DOC = invoice present.
        // The checkbox logic can be refined once document sub-types are added.

        $data = [
            'date'               => now()->format('d/m/Y'),
            'merchantName'       => $importRequest->merchant?->name ?? '—',
            'businessActivity'   => $importRequest->merchant?->business_type ?? '—',
            'invoiceAmount'      => $importRequest->amount ?? 0,
            'amount'             => $importRequest->amount ?? 0,
            'currency'           => $importRequest->currency ?? 'USD',
            'paymentTerms'       => $importRequest->payment_terms ?? '—',
            'goodsType'          => $importRequest->goods_type ?? '—',
            'arrivalPort'        => $importRequest->arrival_port ?? '—',
            'quantity'           => $importRequest->quantity,
            'bankName'           => $importRequest->bank?->name ?? '—',
            'hasTaxCard'         => $hasTaxCard,
            'hasProformaInvoice' => $hasProformaInvoice,
        ];

        $filename = 'confirmation-request-' . ($importRequest->reference_number ?? $importRequest->id) . '.pdf';

        return $this->pdf->download($filename, 'pdf.confirmation-request', $data);
    }

    /**
     * GET /api/requests/{importRequest}/fx-confirmation-template
     *
     * Generates a pre-filled Document 2 PDF for COMMITTEE_DIRECTOR to download, stamp, sign, and re-upload.
     * Request must be in EXECUTIVE_APPROVED status.
     * Accessible to: COMMITTEE_DIRECTOR only.
     */
    public function fxConfirmation(ImportRequest $importRequest)
    {
        $user = request()->user();

        if ($user->role !== UserRole::COMMITTEE_DIRECTOR) {
            throw new AuthorizationException();
        }

        if ($importRequest->status->value !== 'EXECUTIVE_APPROVED') {
            abort(422, 'FX confirmation template is only available for EXECUTIVE_APPROVED requests.');
        }

        $importRequest->load(['merchant', 'bank']);

        $data = [
            'date'           => now()->format('d/m/Y'),
            'merchantName'   => $importRequest->merchant?->name ?? '—',
            'taxNumber'      => $importRequest->merchant?->tax_number ?? null,
            'documentNumber' => '',           // left blank on template; filled on final issuance
            'goodsType'      => $importRequest->goods_type ?? '—',
            'currency'       => $importRequest->currency ?? 'USD',
            'amount'         => $importRequest->amount ?? 0,
            'yerEquivalent'  => $importRequest->yer_equivalent,
            'arrivalPort'    => $importRequest->arrival_port ?? '—',
            'quantity'       => $importRequest->quantity,
            'directorName'   => $user->name,
            'logoPath'       => public_path('brand/yemen-emblem.png'),
        ];

        $filename = 'fx-confirmation-template-' . ($importRequest->reference_number ?? $importRequest->id) . '.pdf';

        return $this->pdf->download($filename, 'pdf.fx-confirmation', $data);
    }
}
```

### 3.11 FxConfirmationUploadRequest (Form Request)

File: `backend/app/Http/Requests/FxConfirmationUploadRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FxConfirmationUploadRequest extends FormRequest
{
    public function authorize(): bool { return true; } // policy checked in controller

    public function rules(): array
    {
        return [
            'signed_document' => [
                'required',
                'file',
                'mimes:pdf',
                'max:10240', // 10 MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'signed_document.required' => 'يجب رفع وثيقة المصارفة الموقّعة.',
            'signed_document.mimes'    => 'يجب أن يكون الملف بصيغة PDF فقط.',
            'signed_document.max'      => 'حجم الملف يتجاوز الحد الأقصى (10MB).',
        ];
    }
}
```

### 3.12 Modified: CustomsController — add upload endpoint

Add a new method `uploadSignedFx` to the existing `CustomsController`:

```php
/**
 * POST /api/requests/{importRequest}/fx-confirmation-upload
 *
 * Director uploads the physically stamped and signed FX confirmation PDF.
 * Transitions request from EXECUTIVE_APPROVED → FX_CONFIRMATION_PENDING.
 * The existing generate() endpoint then consumes this to issue the final document.
 */
public function uploadSignedFx(
    ImportRequest $importRequest,
    FxConfirmationUploadRequest $request
) {
    $user = request()->user();

    if ($user->role !== UserRole::COMMITTEE_DIRECTOR) {
        throw new AuthorizationException();
    }

    if ($importRequest->status->value !== 'EXECUTIVE_APPROVED') {
        return ApiResponse::error('يجب أن يكون الطلب في حالة EXECUTIVE_APPROVED.', 422, 'WORKFLOW_INVALID_STATE');
    }

    DB::transaction(function () use ($importRequest, $request, $user) {
        // Store signed PDF in private storage
        $file         = $request->file('signed_document');
        $relativePath = "fx-confirmations/{$importRequest->id}/" . uniqid() . '.pdf';
        Storage::disk('local')->put('private/' . $relativePath, file_get_contents($file->getRealPath()));

        // Upsert the customs_declarations record with signed doc info
        // (creates the record if it doesn't exist yet; updates if it does)
        $declaration = CustomsDeclaration::firstOrCreate(
            ['request_id' => $importRequest->id],
            [
                'declaration_number'         => '',  // filled on final issuance
                'issued_by'                  => $user->id,
                'issued_at'                  => now(),
                'pdf_path'                   => '',
                'metadata'                   => [],
            ]
        );

        $declaration->update([
            'signed_fx_doc_path'         => $relativePath,
            'signed_fx_doc_uploaded_at'  => now(),
            'signed_fx_doc_uploaded_by'  => $user->id,
        ]);

        // Transition to FX_CONFIRMATION_PENDING
        $this->customsService->uploadSignedFxDoc($importRequest, $declaration, $user);
    });

    return ApiResponse::success(null, 'تم رفع وثيقة المصارفة الموقّعة بنجاح.', 200);
}
```

### 3.13 Modified: CustomsService — add uploadSignedFxDoc + gate in generate()

In `CustomsService`, add:

```php
/**
 * Record the signed FX doc upload and transition to FX_CONFIRMATION_PENDING.
 */
public function uploadSignedFxDoc(
    ImportRequest $request,
    CustomsDeclaration $declaration,
    User $uploader
): void {
    $this->workflowService->transition($request, 'upload_fx_confirmation', $uploader);

    $this->auditService->log(
        AuditAction::FX_CONFIRMATION_UPLOADED,
        $uploader,
        $request,
        ['declaration_id' => $declaration->id]
    );
}
```

In the existing `generate()` method, add a guard after the `lockForUpdate()` block:

```php
// After line: if ($lockedRequest->status !== RequestStatus::EXECUTIVE_APPROVED)
// Change the status check to accept FX_CONFIRMATION_PENDING:

if (!in_array($lockedRequest->status, [
    RequestStatus::EXECUTIVE_APPROVED,
    RequestStatus::FX_CONFIRMATION_PENDING,
])) {
    throw new CustomsException('Customs declaration can only be generated for EXECUTIVE_APPROVED or FX_CONFIRMATION_PENDING requests.');
}

// Require the signed document before proceeding
$declaration = $lockedRequest->customsDeclaration()->first();
if ($declaration === null || $declaration->signed_fx_doc_path === null) {
    throw new CustomsException('Signed FX confirmation document must be uploaded before issuing.');
}
```

Also update `generate()` to update the existing `$declaration` record (created by `uploadSignedFx`) rather than calling `CustomsDeclaration::query()->create()`. Use `$declaration->update([...])` to fill in `declaration_number`, `issued_at`, `pdf_path`, `metadata`.

And update the audit log action from `CUSTOMS_ISSUED` to `FX_CONFIRMATION_ISSUED` for new issuances.

### 3.14 WorkflowService — add FX_CONFIRMATION_PENDING transition

In `backend/app/Services/Workflow/WorkflowService.php`, add the transition entry:

```
'upload_fx_confirmation' => [
    'from'    => RequestStatus::EXECUTIVE_APPROVED,
    'to'      => RequestStatus::FX_CONFIRMATION_PENDING,
    'action'  => WorkflowAction::UPLOAD_FX_CONFIRMATION,
    'actor_column' => null,   // no actor column for this transition
],
```

The existing `issue_customs` transition must be updated to accept `FX_CONFIRMATION_PENDING` as a valid `from` status (currently it likely only accepts `EXECUTIVE_APPROVED`):

```
'issue_customs' => [
    'from'    => [RequestStatus::EXECUTIVE_APPROVED, RequestStatus::FX_CONFIRMATION_PENDING],
    'to'      => RequestStatus::CUSTOMS_DECLARATION_ISSUED,
    ...
],
```

### 3.15 Routes

File: `backend/routes/api.php`

```php
// Document template downloads
Route::get(
    '/requests/{importRequest}/confirmation-request-template',
    [DocumentTemplateController::class, 'confirmationRequest']
)->middleware('auth:sanctum');

Route::get(
    '/requests/{importRequest}/fx-confirmation-template',
    [DocumentTemplateController::class, 'fxConfirmation']
)->middleware('auth:sanctum');

// FX confirmation signed document upload
Route::post(
    '/requests/{importRequest}/fx-confirmation-upload',
    [CustomsController::class, 'uploadSignedFx']
)->middleware('auth:sanctum');
```

---

## Section 4 — Frontend Changes

### 4.1 Types: enums.ts

File: `frontend/app/types/enums.ts`

Add to `RequestStatus` enum:
```typescript
FX_CONFIRMATION_PENDING = 'FX_CONFIRMATION_PENDING',
```

### 4.2 Modified: useRequestWizard composable

File: `frontend/app/composables/useRequestWizard.ts`

#### Changes to WizardStep3Data interface

```typescript
export interface WizardStep3Data {
  confirmation_request: File | null   // ← ADD (mandatory)
  proforma_invoice:     File | null
  commercial_register:  File | null
  tax_card:             File | null
  extra_docs:           File | null
}
```

#### Changes to WizardUploadState interface

```typescript
export interface WizardUploadState {
  confirmation_request: 'idle' | 'uploading' | 'done' | 'error'  // ← ADD
  proforma_invoice:     'idle' | 'uploading' | 'done' | 'error'
  commercial_register:  'idle' | 'uploading' | 'done' | 'error'
  tax_card:             'idle' | 'uploading' | 'done' | 'error'
  extra_docs:           'idle' | 'uploading' | 'done' | 'error'
}
```

#### Changes to DOCUMENT_LABELS

```typescript
const DOCUMENT_LABELS: Record<WizardDocumentKey, string> = {
  confirmation_request: 'طلب وثيقة التأكيد (مختوم)',   // ← ADD
  proforma_invoice:     'الفاتورة الأولية',
  commercial_register:  'السجل التجاري',
  tax_card:             'البطاقة الضريبية',
  extra_docs:           'مستندات إضافية',
}
```

#### Changes to initial step3 state

```typescript
const step3 = ref<WizardStep3Data>({
  confirmation_request: null,   // ← ADD
  proforma_invoice:     null,
  commercial_register:  null,
  tax_card:             null,
  extra_docs:           null,
})
```

#### Changes to initial uploadState

```typescript
const uploadState = ref<WizardUploadState>({
  confirmation_request: 'idle',   // ← ADD
  proforma_invoice:     'idle',
  commercial_register:  'idle',
  tax_card:             'idle',
  extra_docs:           'idle',
})
```

#### Changes to step 3 validation (in `validateStep3()` or wherever required-doc check runs)

The validation check for required documents must include `confirmation_request`. Locate the existing check for `proforma_invoice`, `commercial_register`, `tax_card` and add `confirmation_request` to the required array.

#### New state: autoSavedForStep3

```typescript
const autoSavedForStep3 = ref(false)   // true once draft is saved when entering step 3
```

#### New function: ensureDraftSavedForStep3

```typescript
async function ensureDraftSavedForStep3(): Promise<void> {
  if (savedRequestId.value !== null) {
    autoSavedForStep3.value = true
    return
  }
  // Auto-save draft so the template download endpoint has an ID to work with
  const result = await saveDraft()
  if (result) {
    autoSavedForStep3.value = true
  }
}
```

Expose this function from `useRequestWizard()` return value.

### 4.3 Modified: WizardStep3.vue

File: `frontend/app/components/wizard/WizardStep3.vue`

#### New props

```typescript
const props = defineProps<{
  modelValue:       WizardStep3Data
  errors:           Partial<Record<WizardDocumentKey, string>>
  uploadState:      WizardUploadState
  loading?:         boolean
  requestId?:       number | null      // ← ADD: needed for template download URL
  templateReady?:   boolean            // ← ADD: true once draft has been auto-saved
}>()
```

#### New ZONES entry (at the top of the array, before proforma_invoice)

```typescript
const ZONES: DocumentZone[] = [
  { key: 'confirmation_request', title: 'طلب وثيقة التأكيد (مختوم)', required: true },
  { key: 'proforma_invoice',     title: 'الفاتورة الأولية (Proforma Invoice)', required: true },
  { key: 'commercial_register',  title: 'السجل التجاري', required: true },
  { key: 'tax_card',             title: 'البطاقة الضريبية', required: true },
  { key: 'extra_docs',           title: 'مستندات إضافية', required: false },
]
```

#### New template section: template download card

Insert **above** the `<div class="grid ...">` zones grid. Use a `Card` with an amber start-border as per the action banner pattern in `frontend/DESIGN.md`.

```vue
<!-- Template download instruction card -->
<Card class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm mb-6">
  <CardContent class="pt-4 pb-4">
    <div class="flex items-start gap-3">
      <FileDown class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)] mt-0.5" aria-hidden="true" />
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-foreground text-sm">
          نموذج طلب وثيقة التأكيد — مطلوب قبل الإرسال
        </p>
        <p class="text-xs text-muted-foreground mt-1">
          حمّل النموذج المعبّأ بالبيانات، اطبعه واختمه بختم البنك، ثم ارفعه في الحقل المخصص أدناه.
        </p>
      </div>
      <Button
        size="sm"
        variant="outline"
        class="flex-shrink-0"
        :disabled="!props.templateReady || !props.requestId"
        @click="downloadTemplate"
      >
        <FileDown class="h-4 w-4 me-1" />
        <span v-if="!props.templateReady">جارٍ التحضير…</span>
        <span v-else>تحميل النموذج</span>
      </Button>
    </div>
  </CardContent>
</Card>
```

Add the download handler in `<script setup>`:

```typescript
import { useRequests } from '../../composables/useRequests'
const { downloadConfirmationRequestTemplate } = useRequests()

async function downloadTemplate(): Promise<void> {
  if (!props.requestId) return
  try {
    const blob = await downloadConfirmationRequestTemplate(props.requestId)
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `confirmation-request-${props.requestId}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch {
    // surface error via toast
    toast.error('تعذّر تحميل النموذج. أعد المحاولة.')
  }
}
```

### 4.4 Modified: RequestWizard.vue

File: `frontend/app/components/wizard/RequestWizard.vue`

#### Auto-save on entering Step 3

In `handleNext()` (called when moving from Step 2 → Step 3), after the step advances, trigger `ensureDraftSavedForStep3()`:

```typescript
async function handleNext(): Promise<void> {
  const ok = wizard.nextStep()
  if (!ok) {
    await new Promise(r => setTimeout(r, 50))
    document.querySelector('[role="alert"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }
  // Auto-save draft when arriving at step 3 so template download is available
  if (wizard.currentStep.value === 3) {
    await wizard.ensureDraftSavedForStep3()
  }
}
```

#### Pass new props to WizardStep3

```vue
<WizardStep3
  v-else-if="wizard.currentStep.value === 3"
  v-model="wizard.step3.value"
  :errors="wizard.step3Errors.value"
  :upload-state="wizard.uploadState.value"
  :loading="wizard.saving.value"
  :request-id="wizard.savedRequestId.value"
  :template-ready="wizard.autoSavedForStep3.value"
  @file-reset="wizard.resetUploadState"
  @update:model-value="(v) => { wizard.step3.value = v }"
/>
```

### 4.5 Modified: useRequests composable

File: `frontend/app/composables/useRequests.ts`

Add these API functions:

```typescript
// Document 1 template download
async function downloadConfirmationRequestTemplate(requestId: number): Promise<Blob> {
  return $fetch<Blob>(`/api/requests/${requestId}/confirmation-request-template`, {
    method: 'GET',
    responseType: 'blob',
  })
}

// Document 2 template download
async function downloadFxConfirmationTemplate(requestId: number): Promise<Blob> {
  return $fetch<Blob>(`/api/requests/${requestId}/fx-confirmation-template`, {
    method: 'GET',
    responseType: 'blob',
  })
}

// Upload signed Document 2
async function uploadSignedFxConfirmation(requestId: number, file: File): Promise<void> {
  const form = new FormData()
  form.append('signed_document', file)
  await $fetch(`/api/requests/${requestId}/fx-confirmation-upload`, {
    method: 'POST',
    body: form,
  })
}
```

Expose all three from the composable return value.

### 4.6 Modified: requests.store.ts

File: `frontend/app/stores/requests.store.ts`

Add state:
```typescript
uploadingSignedFx: false,
signedFxUploaded: false,
```

Add action:
```typescript
async uploadSignedFxDoc(requestId: number, file: File): Promise<void> {
  this.uploadingSignedFx = true
  try {
    const { uploadSignedFxConfirmation } = useRequests()
    await uploadSignedFxConfirmation(requestId, file)
    this.signedFxUploaded = true
    // Refresh the request so status updates to FX_CONFIRMATION_PENDING
    await this.fetchRequest(requestId)
  } finally {
    this.uploadingSignedFx = false
  }
},
```

### 4.7 New component: FxConfirmationCard.vue

File: `frontend/app/components/requests/FxConfirmationCard.vue`

This card appears on the request detail page when:
- `userRole === UserRole.COMMITTEE_DIRECTOR`
- `request.status === RequestStatus.EXECUTIVE_APPROVED || request.status === RequestStatus.FX_CONFIRMATION_PENDING`

It replaces the current bare `AlertDialog` button in `ActionsPanel.vue` for the Director customs action.

**Component responsibilities:**
1. "تحميل النموذج المعبأ" button → calls `downloadFxConfirmationTemplate`
2. PDF upload zone → drag-drop or click-to-browse, PDF-only, ≤10MB
3. Upload button → calls `requestsStore.uploadSignedFxDoc`
4. "إصدار وثيقة تأكيد المصارفة الخارجية" button → calls `requestsStore.issueCustomsDeclaration` — **disabled** until a signed file has been uploaded (tracked by `request.status === FX_CONFIRMATION_PENDING` OR local upload success flag)

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileDown, Upload, CheckCircle2, AlertTriangle, Loader2, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import type { ImportRequest } from '../../types/models'
import { RequestStatus } from '../../types/enums'
import { useRequests } from '../../composables/useRequests'
import { useRequestsStore } from '../../stores/requests.store'
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Alert, AlertDescription } from '../ui/alert'
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel,
  AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '../ui/alert-dialog'

const props = defineProps<{ request: ImportRequest }>()
const emit  = defineEmits<{ 'action-completed': [] }>()

const requestsStore = useRequestsStore()
const { downloadFxConfirmationTemplate } = useRequests()

const signedFile      = ref<File | null>(null)
const dragOver        = ref(false)
const fileError       = ref('')
const downloadError   = ref('')
const uploadError     = ref('')

// The issue button is enabled when the request is already in FX_CONFIRMATION_PENDING
// (meaning a signed doc was uploaded in a previous session) OR we just uploaded one now.
const canIssue = computed(() =>
  props.request.status === RequestStatus.FX_CONFIRMATION_PENDING
  || (signedFile.value !== null && requestsStore.signedFxUploaded)
)

const MAX_MB = 10

function validateFile(file: File): string | null {
  if (!file.name.toLowerCase().endsWith('.pdf') && file.type !== 'application/pdf')
    return 'يجب أن يكون الملف بصيغة PDF فقط'
  if (file.size > MAX_MB * 1024 * 1024)
    return `حجم الملف يتجاوز ${MAX_MB}MB`
  return null
}

function handleFile(file: File | null) {
  fileError.value = ''
  uploadError.value = ''
  if (!file) return
  const err = validateFile(file)
  if (err) { fileError.value = err; return }
  signedFile.value = file
}

function onInputChange(e: Event) {
  handleFile((e.target as HTMLInputElement).files?.[0] ?? null)
}
function onDrop(e: DragEvent) {
  dragOver.value = false
  e.preventDefault()
  handleFile(e.dataTransfer?.files?.[0] ?? null)
}

async function handleDownloadTemplate() {
  downloadError.value = ''
  try {
    const blob = await downloadFxConfirmationTemplate(props.request.id)
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href = url
    a.download = `fx-confirmation-template-${props.request.reference_number}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch {
    downloadError.value = 'تعذّر تحميل النموذج. أعد المحاولة.'
  }
}

async function handleUpload() {
  if (!signedFile.value) return
  uploadError.value = ''
  try {
    await requestsStore.uploadSignedFxDoc(props.request.id, signedFile.value)
    toast.success('تم رفع الوثيقة الموقّعة بنجاح. يمكنك الآن إصدار التأكيد.')
    emit('action-completed')
  } catch (err: unknown) {
    uploadError.value = err instanceof Error ? err.message : 'تعذّر رفع الوثيقة.'
  }
}

async function handleIssue() {
  try {
    await requestsStore.issueCustomsDeclaration(props.request.id)
    emit('action-completed')
  } catch (err: unknown) {
    toast.error(err instanceof Error ? err.message : 'تعذّر إصدار التأكيد.')
  }
}
</script>

<template>
  <Card class="border border-border shadow-sm" role="region" aria-label="إصدار وثيقة تأكيد المصارفة الخارجية">
    <CardHeader class="pb-2">
      <CardTitle class="text-sm font-semibold">
        إصدار وثيقة تأكيد مصارفة / تغطية خارجية
      </CardTitle>
    </CardHeader>
    <CardContent class="space-y-4">

      <!-- Step 1: Download template -->
      <div class="space-y-1">
        <p class="text-xs text-muted-foreground">
          الخطوة 1 — حمّل النموذج المعبّأ بالبيانات، اطبعه، اختمه بختم اللجنة ووقّعه، ثم امسحه ضوئياً بصيغة PDF.
        </p>
        <Button variant="outline" size="sm" @click="handleDownloadTemplate">
          <FileDown class="h-4 w-4 me-1" />
          تحميل النموذج المعبّأ
        </Button>
        <p v-if="downloadError" class="text-xs text-[var(--color-text-error)]">{{ downloadError }}</p>
      </div>

      <div class="h-px bg-border" />

      <!-- Step 2: Upload signed PDF -->
      <div class="space-y-2">
        <p class="text-xs text-muted-foreground">
          الخطوة 2 — ارفع الوثيقة بعد الختم والتوقيع (PDF — {{ MAX_MB }}MB كحد أقصى).
        </p>

        <!-- Drop zone -->
        <div
          v-if="!signedFile"
          class="relative min-h-28 p-4 border-2 border-dashed rounded-lg transition-colors cursor-pointer"
          :class="{
            'border-primary bg-primary/10': dragOver,
            'border-[var(--severity-red)] bg-[var(--color-surface-error)]': fileError,
            'border-border bg-muted/40': !dragOver && !fileError,
          }"
          @dragover.prevent="dragOver = true"
          @dragleave="dragOver = false"
          @drop="onDrop"
        >
          <div class="flex flex-col items-center justify-center h-full gap-2 text-center">
            <Upload class="h-6 w-6 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">اسحب الملف هنا أو</p>
            <label>
              <Button type="button" variant="outline" size="sm" as-child>
                <span>اضغط للاختيار</span>
              </Button>
              <input type="file" accept=".pdf" class="sr-only" @change="onInputChange" />
            </label>
          </div>
          <p v-if="fileError" class="text-xs text-[var(--color-text-error)] text-center mt-2">{{ fileError }}</p>
        </div>

        <!-- File selected -->
        <div
          v-else
          class="flex items-center justify-between p-3 border rounded-lg bg-[var(--color-surface-success)]"
        >
          <div class="flex items-center gap-2">
            <CheckCircle2 class="h-4 w-4 text-[var(--color-text-success)]" />
            <span class="text-sm text-[var(--color-text-success)] font-medium">{{ signedFile.name }}</span>
          </div>
          <Button variant="ghost" size="icon" class="h-6 w-6 text-muted-foreground" @click="signedFile = null">
            <X class="h-4 w-4" />
          </Button>
        </div>

        <!-- Upload button -->
        <Button
          v-if="signedFile && !requestsStore.signedFxUploaded"
          size="sm"
          :disabled="requestsStore.uploadingSignedFx"
          @click="handleUpload"
        >
          <Loader2 v-if="requestsStore.uploadingSignedFx" class="h-4 w-4 me-1 animate-spin" />
          {{ requestsStore.uploadingSignedFx ? 'جارٍ الرفع…' : 'رفع الوثيقة' }}
        </Button>

        <Alert v-if="uploadError" class="border-[var(--severity-red)] bg-[var(--color-surface-error)]">
          <AlertDescription class="text-[var(--color-text-error)]">{{ uploadError }}</AlertDescription>
        </Alert>

        <!-- Already uploaded indicator (from previous session) -->
        <div
          v-if="request.status === RequestStatus.FX_CONFIRMATION_PENDING && !signedFile"
          class="flex items-center gap-2 text-xs text-[var(--color-text-success)]"
        >
          <CheckCircle2 class="h-4 w-4" />
          تم رفع الوثيقة الموقّعة في جلسة سابقة. يمكنك الآن الإصدار.
        </div>
      </div>

      <div class="h-px bg-border" />

      <!-- Step 3: Issue -->
      <div class="space-y-1">
        <p class="text-xs text-muted-foreground">
          الخطوة 3 — بعد رفع الوثيقة الموقّعة، أصدر التأكيد النهائي. هذا الإجراء لا يمكن التراجع عنه.
        </p>
        <AlertDialog>
          <AlertDialogTrigger as-child>
            <Button :disabled="!canIssue || requestsStore.issuingCustoms">
              <Loader2 v-if="requestsStore.issuingCustoms" class="h-4 w-4 me-1 animate-spin" />
              {{ requestsStore.issuingCustoms ? 'جارٍ الإصدار…' : 'إصدار وثيقة تأكيد المصارفة الخارجية' }}
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>تأكيد إصدار وثيقة المصارفة الخارجية</AlertDialogTitle>
              <AlertDialogDescription>
                سيتم إصدار وثيقة تأكيد المصارفة الخارجية وإتمام معالجة الطلب نهائياً. هذا الإجراء لا يمكن التراجع عنه.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>إلغاء</AlertDialogCancel>
              <AlertDialogAction
                :disabled="requestsStore.issuingCustoms"
                @click="handleIssue"
              >
                <Loader2 v-if="requestsStore.issuingCustoms" class="h-4 w-4 me-1 animate-spin" />
                {{ requestsStore.issuingCustoms ? 'جارٍ الإصدار…' : 'تأكيد الإصدار' }}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>

    </CardContent>
  </Card>
</template>
```

### 4.8 Modified: Request detail page

File: `frontend/app/pages/requests/[id]/index.vue`

Replace the bare director customs `AlertDialog` that's currently inside `ActionsPanel.vue` with a mount of `FxConfirmationCard`:

In the request detail page, alongside the `ActionsPanel`, add a conditional block:

```vue
<FxConfirmationCard
  v-if="isDirectorCustomsPhase"
  :request="request"
  @action-completed="handleActionCompleted"
/>
```

Where:
```typescript
const isDirectorCustomsPhase = computed(() =>
  authStore.user?.role === UserRole.COMMITTEE_DIRECTOR
  && (
    request.value?.status === RequestStatus.EXECUTIVE_APPROVED
    || request.value?.status === RequestStatus.FX_CONFIRMATION_PENDING
  )
)
```

Remove the `showDirectorCustomsActions` block from `ActionsPanel.vue` (lines covering `EXECUTIVE_APPROVED → issue FX confirmation`). `FxConfirmationCard` fully replaces it.

---

## Section 5 — Error Cases

| Scenario | Backend response | Frontend handling |
|---|---|---|
| Template download before draft is saved | Should not happen — button disabled until `templateReady` | Button disabled state |
| Template download for wrong bank | 403 AuthorizationException | `toast.error` |
| FX upload when status is not EXECUTIVE_APPROVED | 422 `WORKFLOW_INVALID_STATE` | `uploadError` shown in card |
| FX upload: file not PDF | 422 validation error | `uploadError` shown |
| FX upload: file > 10MB | 422 validation error | `uploadError` shown |
| Issue when no signed doc uploaded | 422 CustomsException | `toast.error` |
| Issue when already issued | 403 / 422 (already exists guard in CustomsService) | `toast.error` |
| mPDF font not found | PHP exception at template render time | 500 → frontend shows generic error |

---

## Section 6 — Acceptance Criteria

### Document 1 (طلب وثيقة التأكيد)

- [ ] DATA_ENTRY navigates to wizard Step 3; if no draft exists the system auto-saves, then enables the "تحميل النموذج" button
- [ ] Clicking download produces a PDF file with correct merchant name, goods type, invoice amount, currency, arrival port, bank name, and today's date pre-filled
- [ ] A new upload zone labeled "طلب وثيقة التأكيد (مختوم)" appears first in the document grid; it is marked required
- [ ] Step 4 and the submit button are blocked if `confirmation_request` is not uploaded
- [ ] The PDF uses the Amiri Arabic font, `#0066cc` color accents, and correct RTL layout — Arabic text renders with connected letters
- [ ] The PDF is A4 portrait with proper margins matching the reference template

### Document 2 (وثيقة تأكيد مصارفة)

- [ ] COMMITTEE_DIRECTOR on a request in `EXECUTIVE_APPROVED` status sees `FxConfirmationCard` on the detail page (not just a bare button)
- [ ] Clicking "تحميل النموذج المعبأ" produces a pre-filled PDF with correct data and Yemen emblem logo
- [ ] The upload zone in the card accepts only PDF files ≤10MB
- [ ] After successful upload the request transitions to `FX_CONFIRMATION_PENDING` and the issue button becomes enabled
- [ ] The issue button remains disabled if the upload has not happened in this session AND the request is still `EXECUTIVE_APPROVED`
- [ ] If the director returns to the page with the request already in `FX_CONFIRMATION_PENDING`, the card shows "تم رفع الوثيقة الموقّعة في جلسة سابقة" and the issue button is enabled immediately
- [ ] Clicking issue and confirming transitions the request through `CUSTOMS_DECLARATION_ISSUED` → `COMPLETED`
- [ ] Both transitions (`EXECUTIVE_APPROVED → FX_CONFIRMATION_PENDING` and `FX_CONFIRMATION_PENDING → CUSTOMS_DECLARATION_ISSUED`) are logged to `audit_logs`

---

## Section 7 — Testing

### Backend tests to add

- `DocumentTemplateController`:
  - DATA_ENTRY of correct bank gets 200 + PDF content-type for confirmation-request-template
  - DATA_ENTRY of wrong bank gets 403
  - BANK_REVIEWER gets 403
  - COMMITTEE_DIRECTOR gets 200 for fx-confirmation-template on EXECUTIVE_APPROVED request
  - COMMITTEE_DIRECTOR gets 422 for fx-confirmation-template on non-EXECUTIVE_APPROVED request
- `CustomsController::uploadSignedFx`:
  - Valid PDF upload transitions to FX_CONFIRMATION_PENDING
  - Non-PDF upload returns 422
  - Wrong role returns 403
  - Wrong status returns 422
- `CustomsService::generate`:
  - Throws CustomsException if no signed doc on record
  - Accepts FX_CONFIRMATION_PENDING as a valid from-status
- `WorkflowService`:
  - EXECUTIVE_APPROVED → FX_CONFIRMATION_PENDING via UPLOAD_FX_CONFIRMATION action
  - FX_CONFIRMATION_PENDING → CUSTOMS_DECLARATION_ISSUED via ISSUE_CUSTOMS action

### Frontend tests to add

- `WizardStep3`: renders `confirmation_request` zone as first required zone
- `WizardStep3`: "تحميل النموذج" button is disabled when `templateReady = false`
- `WizardStep3`: "تحميل النموذج" button is enabled when `templateReady = true` and `requestId` is set
- `RequestWizard`: calls `ensureDraftSavedForStep3` when advancing from step 2 to step 3
- `FxConfirmationCard`: renders three-step layout for EXECUTIVE_APPROVED status
- `FxConfirmationCard`: issue button is disabled when status is EXECUTIVE_APPROVED and no file uploaded
- `FxConfirmationCard`: issue button is enabled when status is FX_CONFIRMATION_PENDING
- `FxConfirmationCard`: upload zone rejects non-PDF files with Arabic error message
- `FxConfirmationCard`: upload zone rejects files > 10MB

---

## Notes for Implementing AI

1. **Run `codebase_search "WorkflowService transition"` before modifying `WorkflowService`** to understand the exact data structure used for the transitions map (it may be a match statement, an array, or a separate method per action).

2. **Check `RequestStatus::isTerminal()`** after adding `FX_CONFIRMATION_PENDING` — it must NOT appear in the terminal list.

3. **mPDF uses its own HTML parser**, not a browser engine. Do not use CSS `display: flex`, `display: grid`, `position: sticky`, or CSS custom properties (`var(--color)`). Use inline hex values and `<table>`-based layout throughout the Blade templates.

4. **Font path**: confirm `resource_path('fonts')` resolves correctly in the deployed environment. If `Amiri-Regular.ttf` is not present, mPDF will fall back to its bundled `freeserif` font which also supports Arabic but has lower quality. The fallback is acceptable for development; for production the Amiri font files must be present.

5. **SVG → PNG**: the `frontend/public/brand/yemen-emblem.svg` file must be exported as PNG to `backend/public/brand/yemen-emblem.png` before Document 2 renders correctly. mPDF's SVG rendering does not support complex path fills found in national emblems.

6. **`FX_REQUEST` DocumentType already exists** — do not repurpose it for Document 1. Document 1 (طلب وثيقة التأكيد) uses the new `CONFIRMATION_REQUEST` type. Document 2's signed upload is stored on `customs_declarations.signed_fx_doc_path`, not in the `request_documents` table.

7. **The existing `ActionsPanel` director block** (`showDirectorCustomsActions`) must be removed after `FxConfirmationCard` is wired into the request detail page to avoid double-rendering the issue button.

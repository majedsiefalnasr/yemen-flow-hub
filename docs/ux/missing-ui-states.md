# Yemen Flow Hub — Missing UI States Design Specification

**Version:** 1.0  
**Date:** 2026-05-18  
**Author:** BMAD UX Design Agent  
**Status:** Approved for implementation  

> All specifications derived from stakeholder-approved Lovable prototype screenshots and `DESIGN.md`.  
> All text is Arabic (RTL). All measurements are in pixels unless noted. Implementation must use `dir="rtl"` on all containers.

---

## Design Token Reference

| Token | Value | Usage |
|-------|-------|-------|
| `primary` | `#0066cc` | Primary CTAs, active elements, focus rings |
| `background` | `#ffffff` | Page canvas |
| `surface-dim` | `#f5f5f5` | Alternate/secondary surfaces |
| `surface-container` | `#e9ecef` | Disabled inputs, containers |
| `on-surface` | `#1c222b` | Primary text |
| `on-surface-variant` | `#6c757d` | Secondary text, labels |
| `outline-variant` | `#cccccc` | Default borders |
| `outline` | `#505050` | Strong/focus borders |
| `error` | `#c62828` | Error text, error borders |
| `error-bg` | `#ffebee` | Error banners, alert backgrounds |
| `error-border` | `#ffcdd2` | Error banner borders |
| `warning-bg` | `#fff8e1` | Warning banners |
| `warning-border` | `#ffe082` | Warning banner borders |
| `warning-text` | `#f57f17` | Warning text |
| `success-text` | `#1b5e20` | Success text |
| `success-bg` | `#f1f8f4` | Success backgrounds |
| `skeleton-base` | `#e0e0e0` | Skeleton loader base color |
| `skeleton-highlight` | `#f5f5f5` | Skeleton shimmer highlight |

**Typography:**
- Headlines (h1–h3): **Cairo**, sizes 28–60px
- Section headers: **Tajawal**, 20px 700
- Body / form labels: **IBM Plex Sans Arabic**, 14–16px
- Captions / timestamps: IBM Plex Sans Arabic, 12px

**Border radii:** inputs 12px · buttons 16px · modals 24px · cards 16px · badges 9999px  
**Shadows:** sm `0 1px 2px rgba(0,0,0,0.06)` · md `0 4px 12px rgba(0,0,0,0.10)` · lg `0 16px 40px rgba(0,0,0,0.12)`

---

## Spec 1 — RequestWizard: 4-Step Request Creation Form

**Component:** `RequestWizard.vue`  
**Route:** `/requests/new`  
**Used by:** `DATA_ENTRY`, `BANK-ADMIN`  
**Layout:** Full page (not a modal). Sidebar + header remain visible.

---

### 1.1 Page Structure

```
[Header: breadcrumb]
الرئيسية / الطلبات / طلب جديد

[Page title — Cairo 28px 600]
تقديم طلب تمويل واردات جديد

[Subtitle — IBM Plex Sans Arabic 14px, #6c757d]
أملأ البيانات بدقة وأرفق المستندات المطلوبة

[Stepper — full width, 4 steps]

[Step content card — surface #ffffff, border 1px #cccccc, radius 16px, shadow sm, padding 24px]

[Bottom navigation bar — sticky bottom, bg #ffffff, border-top 1px #cccccc, padding 16px 24px]
```

---

### 1.2 Stepper Component

**Layout:** Horizontal, full-width, centered. Steps connected by horizontal lines.

```
  ①——————————②——————————③——————————④
بيانات الطلب   المورد    الوثائق   المراجعة
```

**Step states:**

| State | Circle style | Label style | Line after |
|-------|-------------|-------------|------------|
| Future | 28px circle, border 2px `#cccccc`, bg `#ffffff`, number `#6c757d` | IBM Plex Sans Arabic 13px `#6c757d` | 2px `#cccccc` |
| Active | 28px circle, bg `#0066cc`, number `#ffffff`, ring `0 0 0 3px rgba(0,102,204,0.2)` | IBM Plex Sans Arabic 13px `#0066cc` 600 | 2px `#cccccc` |
| Completed | 28px circle, bg `#1b5e20`, checkmark icon `#ffffff` | IBM Plex Sans Arabic 13px `#1b5e20` | 2px `#1b5e20` |

**Step labels:**
1. بيانات الطلب
2. بيانات المورد والشحنة
3. الوثائق المطلوبة
4. المراجعة والإرسال

**Stepper padding:** 24px top and bottom. Line thickness 2px. Circle is centered on the line.

---

### 1.3 Step 1 — بيانات الطلب (Basic Information)

**Section header:** Tajawal 20px 700 `#1c222b` — "معلومات الطلب الأساسية"

**Field layout:** 2-column grid (gap 24px), full-width on mobile.

| Field | Type | Required | Options / Notes |
|-------|------|----------|-----------------|
| نوع الواردات | Select | ✓ | مواد غذائية / أدوية ومستلزمات طبية / منتقلات نفطية / قطع غيار / أخرى |
| مبلغ التمويل | Number input | ✓ | Inline currency selector on trailing edge (USD/EUR/SAR). Min 1,000. No negative. |
| العملة | Select | ✓ | USD دولار أمريكي / EUR يورو / SAR ريال سعودي. Pre-synced with mبلغ التمويل inline selector. |
| شروط الدفع | Select | ✓ | L/C اعتماد مستندي / T/T تحويل بنكي مباشر / CAD نقداً عند التسليم |
| تاريخ الاستحقاق المتوقع | Date picker | — | Must be future date. Calendar popover, RTL layout. |
| المستورد (التاجر) | Searchable select | ✓ | **BANK-ADMIN:** searchable dropdown from registered merchants. **DATA_ENTRY:** read-only field, pre-filled from their organisation — shown as plain text with lock icon, not editable. |
| ملاحظات إضافية | Textarea | — | 3 rows. Placeholder: "أي معلومات إضافية تتعلق بالطلب...". Max 500 chars. Counter "N/500" shown below field. |

**مبلغ التمويل + العملة compound field:**
```
[  850,000          ] [ USD ▼ ]
 ←————————————————→   ←——→
     flex-grow:1       96px
```
Border radius: 12px on the number input right corners (RTL leading), 12px on the currency selector left corners (RTL trailing). Shared border between them (no double border).

**DATA_ENTRY merchant field (read-only):**
```
[🔒  شركة هائل سعيد أنعم — مواد غذائية  ]
bg: #f5f5f5, text: #1c222b, border: 1px #cccccc, cursor: not-allowed
```

---

### 1.4 Step 2 — بيانات المورد والشحنة (Supplier & Shipment)

**Section header:** Tajawal 20px 700 — "بيانات المورد والشحنة"

**Field layout:** 2-column grid (gap 24px).

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| اسم المورد | Text | ✓ | Free text. Placeholder: "مثال: Cargill Trading Inc." |
| رقم الفاتورة | Text | ✓ | Placeholder: "INV-2025-XXXX". Uppercase enforcement recommended. |
| بلد المنشأ | Searchable select | ✓ | Full country list, Arabic names, searchable. |
| تاريخ الفاتورة | Date picker | ✓ | Can be past or future. |
| ميناء الوصول | Select | ✓ | ميناء عدن / ميناء الحديدة / ميناء المكلا |
| ميناء الشحن | Text | — | Free text. Placeholder: "Port of Houston, USA". |
| الجمارك المختصة | Select | — | Auto-populated based on ميناء الوصول selection: عدن→جمارك عدن, الحديدة→جمارك الحديدة, المكلا→جمارك المكلا. Editable override allowed. |
| رقم بوليصة الشحن | Text | — | Placeholder: "BL-XXXX-XXXX". |

**Auto-fill behaviour:** When ميناء الوصول changes, الجمارك المختصة field auto-fills with the mapped value and shows a blue "تم التعبئة التلقائية" chip that fades after 2 seconds. User can still override.

---

### 1.5 Step 3 — الوثائق المطلوبة (Required Documents)

**Section header:** Tajawal 20px 700 — "رفع الوثائق المطلوبة"

**Layout:** 2×2 grid of upload zones. On mobile: single column.

**Upload Zone (idle state):**
```
┌ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┐
|  [إلزامي]  or  [اختياري]                  [↑]  |  ← badge top-right, upload icon top-left
|                                                 |
|           ⬆  أسقط الملف هنا                   |
|         أو                                      |
|    [  أضغط للرفع  ]  ← ghost button           |
|                                                 |
|  📄  الفاتورة الأولية (Proforma Invoice)       |  ← title, Tajawal 14px 600
|  PDF, JPG — مد أقصى 10MB                      |  ← hint, IBM Plex 12px #6c757d
└ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┘
```
- Border: 2px dashed `#cccccc`
- Radius: 12px
- Background: `#fafafa`
- Padding: 20px 16px
- Min height: 140px

**Drag-over state:**
- Border: 2px dashed `#0066cc`
- Background: `#e3f2fd`
- Text color shifts to `#0066cc`

**Uploaded state:**
```
┌──────────────────────────────────────────────┐
|  ✓  الفاتورة الأولية (Proforma Invoice)       |  ← title, green #1b5e20
|                                               |
|  📎  invoice-2025-001.pdf   1.2 MB   [✗]     |  ← file chip with remove button
└──────────────────────────────────────────────┘
```
- Border: 2px solid `#1b5e20`
- Background: `#f1f8f4`

**Upload error state:**
- Border: 2px solid `#c62828`
- Background: `#ffebee`
- Error text below zone: `⚠ حجم الملف يتجاوز الحد الأقصى (10MB)`

**Document zones:**

| # | Title | Type Badge | Required |
|---|-------|-----------|----------|
| 1 | الفاتورة الأولية (Proforma Invoice) | إلزامي | ✓ |
| 2 | السجل التجاري | إلزامي | ✓ |
| 3 | البطاقة الضريبية | إلزامي | ✓ |
| 4 | مستندات إضافية | اختياري | — |

**إلزامي badge:** bg `#ffebee`, text `#c62828`, border `#ffcdd2`, radius 9999px, 4px 10px  
**اختياري badge:** bg `#e3f2fd`, text `#0d47a1`, border `#bbdefb`, radius 9999px, 4px 10px

**File constraints:** PDF or JPG only. Max 10MB per file. Client-side validation before upload attempt.

---

### 1.6 Step 4 — المراجعة والإرسال (Review & Submit)

**Section header:** Tajawal 20px 700 — "مراجعة الطلب قبل الإرسال"

**Summary card layout:**
```
┌──────────────────────────────────────────────┐
│  بيانات الطلب                               │  ← section heading Tajawal 16px 700
│ ─────────────────────────────────────────── │
│  نوع الواردات        مواد غذائية            │
│  المستورد            شركة هائل سعيد أنعم    │
│  المبلغ              USD 850,000             │
│  شروط الدفع          L/C                    │
│                                              │
│  بيانات المورد والشحنة                       │
│ ─────────────────────────────────────────── │
│  المورد              Cargill Trading Inc.    │
│  رقم الفاتورة        INV-2025-3170          │
│  ميناء الوصول        ميناء عدن              │
│  بلد المنشأ          الولايات المتحدة        │
│                                              │
│  الوثائق المرفوعة                            │
│ ─────────────────────────────────────────── │
│  ✓ الفاتورة الأولية   invoice.pdf           │
│  ✓ السجل التجاري      register.pdf          │
│  ✓ البطاقة الضريبية   tax-card.pdf         │
└──────────────────────────────────────────────┘
```
- Each key-value pair: key = IBM Plex Sans Arabic 14px `#6c757d`, value = IBM Plex Sans Arabic 14px 500 `#1c222b`
- Section divider: 1px `#cccccc`
- Read-only. No edit affordances in this step (user must go "السابق" to edit).

**Acknowledgment checkbox:**
```
┌──────────────────────────────────────────────────────────────────┐
│  ☐  أُقر بأن جميع البيانات والمستندات المقدمة صحيحة وكاملة،   │
│     وأتحمل المسؤولية القانونية عن أي بيانات غير دقيقة أو        │
│     مستندات مزوّرة، وفقاً للوائح البنك المركزي اليمني.         │
└──────────────────────────────────────────────────────────────────┘
```
- Checkbox size: 20px × 20px, radius 4px
- Unchecked: border 2px `#cccccc`
- Checked: bg `#0066cc`, checkmark `#ffffff`
- Text: IBM Plex Sans Arabic 14px `#1c222b`
- Container: bg `#fff8e1`, border 1px `#ffe082`, radius 12px, padding 16px, margin-bottom 24px

**Submit button state when checkbox unchecked:**
- Background: `#e9ecef`, text: `#6c757d`, cursor: `not-allowed` (disabled)

**Submit button state when checkbox checked:**
- Background: `#0066cc`, text: `#ffffff`, cursor: `pointer` (active primary)

---

### 1.7 Bottom Navigation Bar

Sticky bottom bar. Background `#ffffff`. Border-top 1px `#cccccc`. Padding 16px 24px. Z-index 10.

**RTL layout (right→left):**
```
[  السابق  ]  ←——————————— flex spacer ————————————→  [ حفظ كمسودة 💾 ]  [ التالي / إرسال للمراجعة → ]
   ghost btn                                             ghost btn (always)     primary btn
   (hidden step 1)
```

| Step | Right (leading in RTL) | Center | Left (trailing in RTL) |
|------|----------------------|--------|------------------------|
| 1 | (hidden) | حفظ كمسودة | التالي ← |
| 2 | → السابق | حفظ كمسودة | التالي ← |
| 3 | → السابق | حفظ كمسودة | التالي ← |
| 4 | → السابق | حفظ كمسودة | إرسال للمراجعة ← (disabled until checkbox) |

**حفظ كمسودة button:** Height 40px, ghost variant, save icon (Lucide `Save`), text "حفظ كمسودة". Always enabled. On click: saves current step data, shows toast "تم الحفظ كمسودة ✓".

---

### 1.8 Step Validation Rules

| Step | Required fields | Validation trigger |
|------|----------------|-------------------|
| 1 | نوع الواردات, مبلغ التمويل, العملة, شروط الدفع, المستورد | "التالي" click |
| 2 | اسم المورد, رقم الفاتورة, بلد المنشأ, تاريخ الفاتورة, ميناء الوصول | "التالي" click |
| 3 | الفاتورة الأولية, السجل التجاري, البطاقة الضريبية (3 required uploads) | "التالي" click |
| 4 | Acknowledgment checkbox | "إرسال للمراجعة" click |

**Behaviour on validation failure:**
1. Show form-level error banner at top of step content card (see Spec 3)
2. Highlight each failing field with error state (red border + error message)
3. Auto-scroll to first failing field
4. Button stays on current step — does NOT advance

---

## Spec 2 — SwiftUploadModal

**Component:** `SwiftUploadModal.vue`  
**Trigger:** "إرفاق وثيقة السويفت" button on SWIFT_OFFICER request detail page  
**Type:** Centered modal dialog  

---

### 2.1 Modal Container

```
Width: 520px (max-width: 90vw on small screens)
Border radius: 24px
Shadow: lg (0 16px 40px rgba(0,0,0,0.12))
Background: #ffffff
Padding: 32px
```

**Backdrop:** `rgba(12, 18, 26, 0.4)` with `backdrop-filter: blur(4px)`

---

### 2.2 Modal Structure

```
┌──────────────────────────────────────────┐
│  رفع وثيقة SWIFT                    [✗] │  ← title Tajawal 20px 700 + close icon
│  ارفع ملف SWIFT الخاص بهذا الطلب       │  ← subtitle IBM 13px #6c757d
│ ─────────────────────────────────────── │
│                                          │
│  وثيقة SWIFT *                          │  ← label IBM Plex 14px 600
│  ┌ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┐ │
│  |    ⬆  أسقط ملف PDF هنا             | │
│  |    أو                               | │
│  |  [ اختر ملفاً ]  ghost btn 36px    | │
│  |  PDF فقط — مد أقصى 10MB            | │
│  └ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┘ │
│                                          │
│  رقم المرجع                             │  ← optional label
│  [                                    ] │  ← text input
│  رقم تحويل SWIFT أو مرجع البنك المركزي  │  ← hint IBM Plex 12px #6c757d
│                                          │
│  ملاحظات                                │  ← optional label
│  [                                    ] │  ← textarea 2 rows
│  [                                    ] │
│                                          │
│  ─────────────────────────────────────  │
│  [  إلغاء  ]        [ تأكيد الرفع ← ] │  ← ghost + primary buttons
└──────────────────────────────────────────┘
```

---

### 2.3 Upload Zone States

**Idle:**
- Border: 2px dashed `#cccccc`, radius 12px, bg `#fafafa`, padding 24px, min-height 120px

**Drag-over:**
- Border: 2px dashed `#0066cc`, bg `#e3f2fd`
- Text: `#0066cc`

**File selected (uploaded):**
```
┌──────────────────────────────────────────────┐
│  ✓  swift-transfer-IMP-2026-2015.pdf        │  ← filename IBM Plex 14px #1b5e20
│     2.4 MB                         [✗]      │  ← size #6c757d + remove button
└──────────────────────────────────────────────┘
```
- Border: 2px solid `#1b5e20`, bg `#f1f8f4`, radius 12px

**Error state:**
- Border: 2px solid `#c62828`, bg `#ffebee`, radius 12px
- Below zone: `⚠ فشل رفع الملف. تحقق من اتصالك وأعد المحاولة.` (IBM Plex 13px `#c62828`)

---

### 2.4 Button States

**تأكيد الرفع:**
- Disabled (no file): bg `#e9ecef`, text `#6c757d`
- Enabled (file ready): bg `#0066cc`, text `#ffffff`, primary variant
- Loading (uploading): spinner icon (Lucide `Loader2`, 16px, spinning) + text "جارٍ الرفع..."

**إلغاء:**
- Always enabled. Ghost variant. Closes modal without saving.

---

### 2.5 Interaction Flow

```
[User clicks "إرفاق وثيقة السويفت"]
        ↓
[Modal opens — backdrop blur]
        ↓
[User drops/selects PDF file]
        ↓
[File validation: type=PDF? size≤10MB?]
   ↙ Pass           ↘ Fail
[Show file chip]   [Show error under zone]
[Enable "تأكيد"]   [Keep "تأكيد" disabled]
        ↓
[User clicks "تأكيد الرفع"]
        ↓
[Button → loading state "جارٍ الرفع..."]
[POST /api/swift/{id}/upload]
   ↙ 200 OK              ↘ Error
[Modal closes]           [Inline error shown]
[Request status →        [Button resets to enabled]
 SWIFT_UPLOADED]
[Toast: "تم رفع وثيقة SWIFT بنجاح ✓"]
```

**Toast spec:** Bottom-right of screen (RTL: bottom-left). Green bg `#f1f8f4`, border `#c8e6c9`, text `#1b5e20`. Icon: Lucide `CheckCircle`. Auto-dismiss after 4 seconds. Width 320px, radius 12px, shadow md.

---

## Spec 3 — Form Validation Error States

**Apply to:** All forms across the entire platform.

---

### 3.1 Field-Level Error State

**Triggered by:** Blur from a required empty field, invalid input, or failed submit attempt.

```
[Field label *]

┌──────────────────────────────────────────────┐
│  [user input or empty]                       │  ← border: 2px solid #c62828
└──────────────────────────────────────────────┘
⚠  هذا الحقل مطلوب                              ← error row below field
```

**Error row specs:**
- Icon: Lucide `AlertCircle`, 16px, `#c62828`, inline with text
- Text: IBM Plex Sans Arabic 12px, `#c62828`
- Margin-top: 4px
- Layout: flex row, gap 4px, align-items center

**Field border:** `2px solid #c62828` (replaces the default `1px solid #cccccc`)  
**Field background:** `#ffffff` — does NOT change (no red fill)  
**Label color:** Remains `#1c222b` — does NOT go red (label color is stable)  

**Valid / cleared state:** Returns to `1px solid #cccccc` border, error row disappears (transition 150ms).

---

### 3.2 Error Message Catalog

| Context | Arabic message |
|---------|---------------|
| Required field empty | هذا الحقل مطلوب |
| Invalid number format | يرجى إدخال رقم صحيح |
| Amount below minimum (1,000) | المبلغ يجب أن يكون 1,000 على الأقل |
| Amount negative | المبلغ يجب أن يكون رقماً موجباً |
| Date in past (when future required) | يجب أن يكون التاريخ في المستقبل |
| Date invalid format | صيغة التاريخ غير صحيحة |
| Max length exceeded | تجاوزت الحد الأقصى المسموح به (N حرف) |
| Min length not met | يجب أن يحتوي على N أحرف على الأقل |
| Invalid email | يرجى إدخال عنوان بريد إلكتروني صحيح |
| Invalid phone | يرجى إدخال رقم هاتف صحيح |
| File type not allowed | يجب أن يكون الملف بصيغة PDF أو JPG فقط |
| File exceeds size limit | حجم الملف يتجاوز الحد الأقصى (10MB) |
| File upload failed (network) | فشل رفع الملف. تحقق من اتصالك وأعد المحاولة. |
| File upload failed (server) | حدث خطأ في الخادم أثناء رفع الملف. حاول مجدداً. |
| Select required (no option chosen) | يرجى اختيار أحد الخيارات |
| Checkbox required (unchecked) | يجب الموافقة على الإقرار للمتابعة |
| Duplicate invoice number | رقم الفاتورة مستخدم بالفعل في طلب آخر |
| Reference not found | لم يتم العثور على هذا المرجع في النظام |

---

### 3.3 Form-Level Error Banner

**Triggered by:** Submit/next-step attempt when one or more fields have errors.

**Position:** Top of the form content card, above the first field group.  
**Behaviour:** Auto-scrolls into view. Dismissed when all errors are resolved.

```
┌──────────────────────────────────────────────────────────┐
│  ⚠  يوجد 3 حقول تحتاج إلى تصحيح قبل المتابعة          │
└──────────────────────────────────────────────────────────┘
```

**Visual spec:**
- Background: `#fff8e1`
- Border: 1px solid `#ffe082`
- Border-radius: 12px
- Padding: 12px 16px
- Icon: Lucide `AlertTriangle`, 18px, `#f57f17`
- Text: IBM Plex Sans Arabic 14px 500, `#f57f17`
- Margin-bottom: 24px (space before first field)

**Dynamic count:** "يوجد (N) حقول" — pluralised correctly. Single error: "يوجد خطأ في حقل واحد".

---

### 3.4 Wizard Step Validation Behaviour

- "التالي" button validates **only current step fields** — not future steps.
- If any current-step field fails: show form-level banner + field errors. Stay on current step.
- If all current-step fields pass: advance to next step. Completed step node turns green.
- **Back navigation:** Clicking "السابق" is always allowed without validation.
- **Draft save:** "حفظ كمسودة" always works — does not require valid fields. Saves partial data.
- **Re-validation:** As user corrects a field and moves focus away (blur), that field re-validates immediately and clears its error if valid.

---

### 3.5 Upload Zone Error States

**Wrong file type:**
```
[Drop zone — error state, border #c62828, bg #ffebee]
⚠  يجب أن يكون الملف بصيغة PDF أو JPG فقط
```

**File too large:**
```
[Drop zone — error state]
⚠  حجم الملف يتجاوز الحد الأقصى (10MB). حجم ملفك: 14.3MB
```

**Upload failed (network):**
```
[Drop zone — shows partial filename chip in error state]
⚠  فشل رفع الملف. تحقق من اتصالك وأعد المحاولة.
[  إعادة المحاولة  ]  ← small ghost button 32px
```

---

## Spec 4 — EmptyState Component

**Component:** `EmptyState.vue`  
**Usage:** Drop-in component for any page or section that has no content to display.  
**Props:** `icon`, `heading`, `subtext`, `ctaLabel?`, `ctaVariant?` (`primary` | `ghost`), `onCtaClick?`

---

### 4.1 Visual Structure

```
         [container — center-aligned, padding 48px 0]

                   [🔲 icon]
                   64px × 64px
                   color: #cccccc

              [heading — Cairo 20px 600 #1c222b]
              max-width: 400px, text-align: center

            [subtext — IBM Plex 14px 400 #6c757d]
              max-width: 320px, text-align: center
                    margin-top: 8px

             [  CTA button  ]  ← optional
              margin-top: 24px
              height: 48px, radius: 16px
```

**Container:** `display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px;`

---

### 4.2 Variants

#### Variant A — No requests yet (`/requests` — empty for this role)

| Property | Value |
|----------|-------|
| Icon | Lucide `FileText` |
| Heading | لا توجد طلبات بعد |
| Subtext | لم يتم تقديم أي طلبات تمويل حتى الآن. |
| CTA | "تقديم طلب جديد" — primary — shown only for DATA_ENTRY and BANK-ADMIN roles |

#### Variant B — Search/filter no results (`/requests` — filtered)

| Property | Value |
|----------|-------|
| Icon | Lucide `Search` |
| Heading | لا توجد نتائج |
| Subtext | لم يتطابق أي طلب مع معايير البحث. جرّب تعديل الفلاتر أو مسحها. |
| CTA | "مسح الفلاتر" — ghost |

#### Variant C — No notifications (`/notifications`)

| Property | Value |
|----------|-------|
| Icon | Lucide `Bell` |
| Heading | لا إشعارات جديدة |
| Subtext | ستظهر هنا جميع تحديثات الطلبات وتنبيهات سير العمل تلقائياً. |
| CTA | None |

#### Variant D — Empty queue (Dashboard for any role)

| Property | Value |
|----------|-------|
| Icon | Lucide `CheckCircle` |
| Heading | قائمة الانتظار فارغة |
| Subtext | لا توجد طلبات تتطلب إجراءً منك في الوقت الحالي. |
| CTA | None |

#### Variant E — No audit logs (`/audit`)

| Property | Value |
|----------|-------|
| Icon | Lucide `Shield` |
| Heading | لا سجلات تدقيق |
| Subtext | لم يتم تسجيل أي نشاط في النظام حتى الآن. |
| CTA | None |

#### Variant F — No merchants (`/merchants`)

| Property | Value |
|----------|-------|
| Icon | Lucide `Building2` |
| Heading | لا يوجد تجار مسجلون |
| Subtext | ابدأ بتسجيل أول تاجر أو مستورد مرتبط بهذا البنك في النظام. |
| CTA | "تسجيل تاجر جديد" — primary — BANK-ADMIN only |

#### Variant G — No staff members (`/staff`)

| Property | Value |
|----------|-------|
| Icon | Lucide `Users` |
| Heading | لا يوجد موظفون مسجلون |
| Subtext | أضف أول عضو في فريق البنك لبدء إدارة الصلاحيات. |
| CTA | "إضافة موظف" — primary — BANK-ADMIN only |

#### Variant H — Search within dropdowns (no results)

| Property | Value |
|----------|-------|
| Icon | Lucide `SearchX` (inline, 20px) |
| Heading | لا توجد نتائج مطابقة |
| Subtext | حاول البحث بكلمات مختلفة. |
| CTA | None |
| Layout | Compact: icon + heading inline, no subtext margin. Height 60px. |

---

### 4.3 Accessibility

- Icon: `aria-hidden="true"` (decorative)
- Heading: `role="heading"` `aria-level="2"`
- CTA: descriptive `aria-label` matching the button text
- Container: `role="status"` when replacing dynamic list content

---

## Spec 5 — Skeleton Loaders

**Component:** `SkeletonLoader.vue` (base shimmer) + named slot compositions  
**Animation:** CSS shimmer — `background: linear-gradient(90deg, #e0e0e0 25%, #f5f5f5 50%, #e0e0e0 75%)`. `background-size: 200% 100%`. `animation: shimmer 1.5s infinite`.

```css
@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
```

All skeleton elements: `border-radius` matching their real counterpart.  
All skeleton elements: `pointer-events: none; user-select: none;`

---

### 5.1 Request List Row Skeleton

**Repeat:** Render 5 skeleton rows while data loads.  
**Row height:** 52px. Matches real table row height.  
**Column widths match real table column proportions (RTL order: right→left):**

```
┌──────┬─────────────────────┬──────────┬────────────────┬──────────────┬──────┐
│ 80px │      160px          │   80px   │  60px × 24px   │ 100px × 8px  │ 24px │
│ bar  │  bar (merchant)     │  bar ($) │  pill (status) │  bar (prog)  │ dot  │
│ 12px │       12px          │   12px   │  radius:9999px │  radius:4px  │  ○   │
└──────┴─────────────────────┴──────────┴────────────────┴──────────────┴──────┘
```

All bars: height 12px, radius 6px (pill-like).  
Action dot: 24px circle, radius 50%.

---

### 5.2 Stat Card Skeleton

**Used in:** Dashboard KPI strip (4 cards).  
**Card:** `#ffffff`, border 1px `#cccccc`, radius 16px, padding 24px, height ~110px.

```
┌─────────────────────────────┐
│  ●  [40px circle]           │  ← icon placeholder
│                             │
│  [████████]  60px × 28px   │  ← number bar, radius 8px
│  [          ] 80px × 12px  │  ← label bar, radius 6px
└─────────────────────────────┘
```

Layout: center-aligned vertically and horizontally per prototype stat card design.

---

### 5.3 Workflow Timeline Skeleton (Right Panel)

**Used in:** Request detail page right panel while request loads.  
**Repeat:** 6 stage entries.

```
Each entry (from top, RTL):
 ○  [————————————] [——————]
20px  120px bar      80px bar
circle  heading      subtext
 │  12px              12px
 │
[connector line: 2px × 24px]
```

Circle: 20px, radius 50%.  
Heading bar: 120px × 12px, radius 6px.  
Subtext bar: 80px × 10px, radius 5px, margin-top 6px.  
Connector: 2px wide × 24px tall, radius 1px, margin-inline-start 9px (aligns with circle center).

---

### 5.4 Table Header Skeleton

**Used in:** Any data table while columns/data loads.

```
[─────] [─────────────] [──────────] [──────────] [───────]
 60px       120px           80px         80px        60px
 12px        12px            12px         12px        12px
```

All bars: height 12px, radius 6px. Evenly spaced in header row. Padding matches table header cell padding (16px horizontal).

---

### 5.5 Card Grid Skeleton (Merchants)

**Used in:** BANK-ADMIN `/merchants` card grid while loading.  
**Repeat:** 4 skeleton cards (2×2 grid).

```
┌─────────────────────────────────────────┐
│  ●  [————————————————] [——]            │  ← avatar + name + badge
│     [——————]                            │  ← subtitle
│     ─────────────────────────────────  │
│  [————————]  [————————]  [————————]    │  ← 3 metadata rows
│  [————————]  [————————]  [————————]    │
│     ─────────────────────────────────  │
│  [───]  [───]  [───]                   │  ← action links
└─────────────────────────────────────────┘
```

Card: `#ffffff`, border 1px `#cccccc`, radius 16px, padding 20px.

---

### 5.6 Loading Strategy (Implementation Notes)

| Page / Component | Skeleton used | Trigger |
|-----------------|--------------|---------|
| `/requests` list | Request list row skeleton × 5 | `pending` state of `useRequests()` |
| Dashboard stat cards | Stat card skeleton × 4 | `pending` state of dashboard composable |
| Request detail right panel | Workflow timeline skeleton | `pending` state of `useRequest(id)` |
| Any data table | Table header skeleton + row skeleton × 5 | `pending` state |
| `/merchants` cards | Card grid skeleton × 4 | `pending` state of `useMerchants()` |

**Minimum display time:** 300ms (prevents flash of skeleton on fast connections). If data resolves faster than 300ms, wait the remainder before showing real content.

**Transition:** Skeleton → real content fades in at 200ms ease. No position jump — skeleton and real content occupy the same space.

---

## Appendix — Component Dependency Map

```
RequestWizard.vue
  ├── StepperBar.vue          ← horizontal stepper with state icons
  ├── Step1BasicInfo.vue      ← form fields, MerchantSelect.vue
  ├── Step2SupplierInfo.vue   ← form fields, CountrySelect.vue
  ├── Step3Documents.vue      ← DocumentUploadZone.vue × 4
  ├── Step4ReviewSubmit.vue   ← SummaryCard.vue, AcknowledgmentCheckbox.vue
  ├── WizardNavBar.vue        ← sticky bottom nav, prev/next/draft
  └── FormErrorBanner.vue     ← shared with all forms

SwiftUploadModal.vue
  ├── DocumentUploadZone.vue  ← reused from Step3
  └── FormErrorBanner.vue

EmptyState.vue                ← standalone, used in all list pages

SkeletonLoader.vue            ← base shimmer primitive
  ├── RequestRowSkeleton.vue
  ├── StatCardSkeleton.vue
  ├── TimelineSkeleton.vue
  ├── TableHeaderSkeleton.vue
  └── CardGridSkeleton.vue
```

**Shared primitives to build first (block all above):**
1. `FormErrorBanner.vue` — needed by all forms
2. `DocumentUploadZone.vue` — needed by RequestWizard Step 3 and SwiftUploadModal
3. `SkeletonLoader.vue` — base shimmer used by all skeletons
4. `EmptyState.vue` — used across 8+ pages

---

*Specification version 1.0 — all designs reference `DESIGN.md` v2026-05-18 and the confirmed stakeholder-approved Lovable prototype screenshots in `lovable/screenshots/`.*

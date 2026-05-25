# DATA_ENTRY — Bank Data Entry Officer

Arabic label: موظف إدخال البيانات

---

## Role Identity

The operational intake user inside a bank. This role prepares import-financing requests, attaches the supporting request documents, corrects requests that come back from internal or CBY review, and tracks the business outcome of submitted requests.

DATA_ENTRY is a **task-oriented operational role**. The UI should feel like a focused work surface — not an analytics console, not a governance dashboard, not a CBY oversight tool.

The role has **no review, approval, voting, claim, SWIFT-upload, or FX-confirmation authority**. The internal bank reviewer (`BANK_REVIEWER`) is a separate person and acts as the bank-side control gate; this segregation is enforced by backend policy and must not be reduced in the UI.

---

## Operational Posture

| Aspect                | Tone                                                                                |
| --------------------- | ----------------------------------------------------------------------------------- |
| Work mode             | Operational / task-oriented                                                         |
| Primary surface       | New-request wizard and the bank's own request list                                  |
| Secondary surface     | Returned-requests queue (correction work)                                           |
| Status language       | Simplified business labels — never raw CBY workflow internals                       |
| Visual density        | Low to medium; forms and document checklists dominate                               |
| Feedback expectations | Strong inline validation, clear correction reasons, friction-free draft persistence |

---

## Scope & Boundary

- **Organization scope:** Own bank only. Cross-bank visibility is forbidden by backend policy and must not be implied anywhere in the UI.
- **Status scope:** All canonical statuses are mapped to a small set of simplified business labels (see Status Presentation below). DATA_ENTRY users should not be exposed to raw CBY enum names as a primary label.
- **Action scope:** Limited to drafting, editing, submitting, and document upload on own-bank requests. Read access continues for the request lifecycle after submission so the user can track outcomes.

---

## Workflow Authority Summary

| Stage                          | DATA_ENTRY authority                                       |
| ------------------------------ | ---------------------------------------------------------- |
| `DRAFT`                        | Create, edit, attach documents, save, submit               |
| `SUBMITTED` / `BANK_REVIEW`    | Read-only; no edits, no withdrawal action                  |
| `BANK_RETURNED`                | Edit and resubmit (correction work)                        |
| `SUPPORT_RETURNED`             | Edit and resubmit (correction work)                        |
| `DRAFT_REJECTED_INTERNAL`      | Edit and resubmit (legacy correction path)                 |
| `BANK_APPROVED` → `COMPLETED`  | Read-only business tracking with simplified status labels  |
| All terminal/rejected statuses | Read-only; no resubmission                                 |
| External FX confirmation       | No access to the generated PDF or signed/stamped re-upload |

---

## Document Authority

| Document                            | Access                |
| ----------------------------------- | --------------------- |
| Request documents (intake)          | Upload + View / Own bank |
| SWIFT document                      | No                    |
| FX confirmation request document    | No                    |
| External FX confirmation PDF        | No                    |

DATA_ENTRY only handles intake-stage documents (Proforma Invoice, Commercial Registry, Tax Card, sector-specific licenses, optional supporting docs). Post-approval and FX-confirmation documents are owned by other roles.

---

## Sidebar Navigation

The sidebar is RTL, fixed on the right side. It is 280 px wide when expanded and collapses to 72 px (icon-only). The header shows the platform name "منصة الواردات" and subtitle "البنك المركزي اليمني". The footer shows the user's name, email, and an avatar with initials; a dropdown provides links to Profile, Settings, and Logout.

| Group              | Item                          | Route          |
| ------------------ | ----------------------------- | -------------- |
| الرئيسية (Main)    | اللوحة الرئيسية (Dashboard)   | /dashboard     |
| الرئيسية           | طلبات التمويل (Requests)      | /requests      |
| الرئيسية           | الإشعارات (Notifications)     | /notifications |
| العمليات (Operations) | تقديم طلب جديد (New Request) | /requests/new  |
| الأخرى (Other)     | الإعدادات (Settings)          | /settings      |

The Administration group is intentionally absent. DATA_ENTRY must not see CBY-side navigation or bank governance surfaces.

---

## Pages

### Login (`/login`)

Standard CBY two-column login. Left column: branding (circular logo, platform name, tagline). Right column: authentication form.

**Authentication form:**

- Email input
- Password input
- "تسجيل الدخول" primary button (#0066cc)

**MFA step:**
If the account has MFA enabled, a second step appears after valid credentials: 6 individual digit cells for the OTP code, a "Verify" button, and a "Back" link.

**Security behavior:**

- Inline field-level validation errors.
- Rate limit: 5 attempts per minute per IP (HTTP 429 with a clear banner).
- Lockout: after 10 consecutive failures, account is locked for 15 minutes; a banner explains the lockout and offers recovery guidance.
- Inactivity redirect: when the user is sent back to login by the inactivity timer, an amber sticky banner reads "انتهت جلستك بسبب عدم النشاط" / "Your session expired due to inactivity."

All failed authentications are logged to the global audit trail with `user_id: NULL` when unauthenticated.

---

### Dashboard (`/dashboard`)

The DATA_ENTRY dashboard is a **task launcher**, not an analytics console. Its job is to answer three questions in less than five seconds:

1. Do I have anything that needs correction?
2. Where are the requests I have already submitted?
3. Can I start a new request quickly?

It deliberately does not expose claim ownership, voting tallies, SWIFT upload controls, or CBY internal stage labels. Those belong to other roles and would create noise here.

---

**Page header:**

- Large greeting: "أهلاً، [first name]"
- Subtitle: "موظف إدخال البيانات بـ[bank name]"
- Primary action (top-right): "+ طلب جديد" (`New Request`) — solid primary blue button, routes to `/requests/new`

---

**Action-required strip (conditional, highest priority):**

When the user has any requests in `BANK_RETURNED`, `SUPPORT_RETURNED`, or `DRAFT_REJECTED_INTERNAL`, an amber alert strip appears directly below the header before any KPI cards.

Content:

- Icon: AlertTriangle (amber)
- Title: "[N] طلبات تحتاج تعديل"
- Subtitle: lists the first returned reference with its return reason snippet (truncated to ~80 chars)
- Action: "ابدأ التعديل" — links to `/requests` pre-filtered to the `returned` tab

This strip is intentionally heavier than the KPI cards because correction work is time-sensitive and easy to miss otherwise. If there are zero returned requests, the strip is hidden entirely (do not show a "no corrections needed" placeholder — that is decorative noise).

---

**KPI cards (4-column grid → 2 on tablet → 1 on mobile):**

Cards use semantic color only. None of them are decorative.

| Card                                       | Source              | Color       | Click-through                            |
| ------------------------------------------ | ------------------- | ----------- | ---------------------------------------- |
| مكتمل / صدر التأكيد (Completed)            | `completed`         | Green       | `/requests?tab=completed`                |
| قيد معالجة CBY (Under CBY Processing)      | `under_cby_processing` | Blue (primary) | `/requests?tab=processing`           |
| بحاجة تعديل (Needs Correction)             | `returned`          | Amber (left-border highlight when > 0) | `/requests?tab=returned` |
| مسودات (Drafts)                            | `draft`             | Gray        | `/requests?tab=draft`                    |

Every card is a clickable affordance with a hover state and `cursor-pointer`. Clicking opens the requests list pre-filtered to the relevant tab.

---

**Quick actions (3-column card grid):**

Each card has an icon, a bold Arabic label, a short subtitle, and a clear primary affordance.

- "إنشاء طلب جديد" — filled primary-blue card → `/requests/new`
- "متابعة طلباتي" — outline card → `/requests`
- "الإشعارات" — outline card with unread-count badge → `/notifications`

---

**Drafts table (compact, max 5 rows):**

Shown only when the user has at least one draft. Columns: Reference Number, Merchant, Last Saved, Continue button. Each row links into `/requests/[id]/edit` (resumes the wizard at the step that was last touched).

Empty state for this section: skip rendering. Do not show "no drafts yet" placeholders — that adds vertical noise.

---

**Recent requests table (compact, max 5 rows):**

Columns: Reference Number, Status (simplified label), Amount, View link.

Status labels available to DATA_ENTRY are restricted to:

| Simplified label                | Underlying statuses                                                                         |
| ------------------------------- | ------------------------------------------------------------------------------------------- |
| مسودة (Draft)                   | `DRAFT`                                                                                     |
| مُعادة (Returned for correction) | `BANK_RETURNED`, `SUPPORT_RETURNED`, `DRAFT_REJECTED_INTERNAL`                              |
| مقدّم للمراجعة (Submitted)      | `SUBMITTED`, `BANK_REVIEW`                                                                  |
| قيد معالجة CBY                  | `BANK_APPROVED`, `SUPPORT_REVIEW_PENDING`, `SUPPORT_REVIEW_IN_PROGRESS`, `SUPPORT_APPROVED`, `WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`, `EXECUTIVE_APPROVED`, `WAITING_FOR_SWIFT`, `SWIFT_UPLOADED`, `FX_CONFIRMATION_PENDING` |
| مرفوض (Rejected)                | `BANK_REJECTED`, `SUPPORT_REJECTED`, `EXECUTIVE_REJECTED`                                   |
| مكتمل (Completed)               | `CUSTOMS_DECLARATION_ISSUED` (legacy), `COMPLETED`                                          |

Raw CBY enum names must never appear in DATA_ENTRY views as the primary status. They may appear only inside the workflow timeline tooltip metadata if exposed at all.

---

**Loading state:**
Skeleton placeholder rows for KPI cards (4 gray pill cards) and 5 skeleton table rows. No spinner overlays.

**Empty state (no requests at all):**
Center column with a friendly illustration, the message "لم تبدأ بعد. ابدأ بأول طلب تمويل واردات."، and a single primary "+ طلب جديد" button. Hide the KPI cards entirely until at least one request exists.

**Error state:**
Inline error card with a "إعادة المحاولة" retry action; the page does not redirect to a global error route.

---

### Requests List (`/requests`)

The DATA_ENTRY request list is the user's portfolio view of their bank's request pipeline. It must remain scannable and free of CBY internal labels.

**Page header:**

- Title: "طلبات تمويل الواردات"
- Subtitle: "طلبات جهتك فقط" (Your entity's requests only)
- Breadcrumbs: الرئيسية → طلبات التمويل
- Primary action: "+ طلب جديد" (DATA_ENTRY only)

---

**Stage tab bar (desktop pill tabs; mobile select):**

Each tab shows a numeric count badge. The order is operational — most actionable first.

| Tab key      | Label                | Mapped statuses                                                                                                       |
| ------------ | -------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `returned`   | مُعادة               | `BANK_RETURNED`, `SUPPORT_RETURNED`, `DRAFT_REJECTED_INTERNAL`                                                        |
| `draft`      | مسودة                | `DRAFT`                                                                                                               |
| `submitted`  | مقدّم                | `SUBMITTED`, `BANK_REVIEW`                                                                                            |
| `processing` | قيد المعالجة         | `BANK_APPROVED` through `FX_CONFIRMATION_PENDING`                                                                     |
| `completed`  | مكتمل                | `CUSTOMS_DECLARATION_ISSUED`, `COMPLETED`                                                                             |
| `rejected`   | مرفوض                | `BANK_REJECTED`, `SUPPORT_REJECTED`, `EXECUTIVE_REJECTED`                                                             |
| `all`        | الكل                 | All of the above                                                                                                      |

The `returned` tab is intentionally first because it represents work the user must do.

---

**Toolbar:**

- Search input (placeholder "ابحث بالمرجع، المستورد، أو رقم الفاتورة")
- Customize Columns dropdown (toggles Merchant, Goods, Amount, Status, Progress)
- Export button (CSV, scoped to the active filter)
- Primary "+ طلب جديد" button (right edge)

**Bulk-select toolbar (replaces search when one or more rows are selected):**

- Selection count chip "تم تحديد [N]"
- Export selected (CSV)
- Print selected
- Clear selection

DATA_ENTRY cannot bulk-submit, bulk-delete, or bulk-edit. Those actions are deliberately not exposed.

---

**Data table:**

| Column           | Description                                                                       |
| ---------------- | --------------------------------------------------------------------------------- |
| (checkbox)       | Bulk selection                                                                    |
| Reference Number | e.g. `REQ-2025-0042` (monospace)                                                  |
| Merchant         | Merchant trade name (hidable)                                                     |
| Goods            | Goods category, e.g. مواد غذائية (hidable)                                        |
| Amount           | Formatted amount + currency, right-aligned (hidable)                              |
| Status           | Colored badge using the simplified business label                                 |
| Progress         | Thin horizontal progress bar showing percentage through the lifecycle             |
| Last Activity    | Relative timestamp (e.g. "منذ ساعتين")                                            |
| Actions          | Row chevron + a contextual primary action when applicable (Edit if `returned`)    |

Row interaction: full-row click navigates to `/requests/[id]`. Hover row shows a subtle background tint and `cursor-pointer`.

**State behavior:**

- **Loading:** 8 skeleton rows.
- **Empty (filtered):** small illustration with "لا توجد طلبات مطابقة للفلتر الحالي" plus a "مسح الفلاتر" link.
- **Empty (no data at all):** centered illustration with "ابدأ بأول طلب تمويل واردات" and the primary "+ طلب جديد" button.
- **Error:** inline error card with retry.

---

### New Request (`/requests/new`)

A guided 4-step wizard for creating and submitting a new import-financing request. Available to DATA_ENTRY by primary navigation; BANK_ADMIN may reach this route as an operational fallback per current implementation.

The wizard's design is intentionally heavy on inline validation, document-completeness indicators, and a save-anytime draft behavior, because intake errors are the most common cause of bank/support returns.

---

**Page header:**

- Title: "تقديم طلب تمويل واردات جديد"
- Subtitle: "املأ البيانات بدقة وأرفق المستندات المطلوبة"
- Breadcrumbs: الرئيسية → طلبات التمويل → طلب جديد

---

**Step progress bar:**

A horizontal stepper card with 4 steps. Completed steps show a green checkmark. The active step circle is filled with primary blue (#0066cc) and ringed. Connector lines turn green when passed.

| Step | Label                                                  |
| ---- | ------------------------------------------------------ |
| 1    | بيانات الطلب (Request Data)                            |
| 2    | بيانات المورد والشحنة (Supplier & Shipment)            |
| 3    | الوثائق المطلوبة (Required Documents)                  |
| 4    | المراجعة والإرسال (Review & Submit)                    |

Step jumps: the user can click any completed step to navigate back. The active step cannot jump forward until validation passes.

---

**Step 1 — Request Data (2-column form grid):**

- Import Type (نوع الواردات) — select: Food, Medicines & Medical Supplies, Petroleum Products, Spare Parts, Construction Materials, Machinery & Equipment
- Importer / Merchant (المستورد) — searchable select from registered merchants (own bank)
- Financing Amount (مبلغ التمويل) — numeric input with thousands separators
- Currency (العملة) — select: USD, EUR, SAR
- Payment Terms (شروط الدفع) — select: L/C, D/P, T/T
- Expected Due Date (تاريخ الاستحقاق المتوقع) — date picker
- Additional Notes (ملاحظات إضافية) — full-width textarea (optional)

Inline validation is real-time. Field-level errors anchor below the input. A step is considered valid when all required fields are filled and within accepted ranges.

---

**Step 2 — Supplier & Shipment (2-column form grid):**

- Supplier Name (اسم المورد) — text input
- Origin Country (بلد المنشأ) — select
- Invoice Number (رقم الفاتورة) — text input
- Invoice Date (تاريخ الفاتورة) — date picker
- Shipping Port (ميناء الشحن) — text input
- Arrival Port (ميناء الوصول) — select: Aden, Hodeidah, Mukalla
- Bill of Lading Number (رقم بوليصة الشحن) — text input
- Customs Office (الجمارك المختصة) — select: Aden, Hodeidah, Mukalla

Duplicate invoice warning (soft): if the invoice number already exists on a non-rejected request for this bank, show an amber inline notice (`تنبيه: رقم الفاتورة مستخدم سابقاً في طلب آخر`) and let the user choose to proceed. Backend keeps final authority on hard blocks.

---

**Step 3 — Required Documents:**

The header row shows a completion indicator: a count badge for missing required documents (e.g. "ناقص: مستندان مطلوبان") plus a green completion chip when all required documents are uploaded.

Document cards are arranged in a 2-column grid; one card per document slot. Required documents depend on goods type:

- Always required: Proforma Invoice, Commercial Registry, Tax Card
- If goods type ∈ {Petroleum Products, Medicines}: sector-specific License (required)
- Optional: Additional Supporting Documents

Each document card includes:

- Icon (Upload before, FileCheck after)
- Document name
- Sub-label: "إلزامي" / "اختياري" · "PDF فقط (حتى 10 ميغابايت)"
- Red "إلزامي" pill if required
- Upload button (outline) — opens file picker; PDF only enforced client-side AND server-side
- After upload: file name, file size, a "تم التحقق" (Verified — SHA-256 checksum confirmed) badge, Preview (eye) button, Remove (trash) button

**Upload error behavior:**

- Non-PDF: inline rejection with "صيغة الملف غير مدعومة — يجب أن تكون PDF فقط"
- File > 10 MB: inline rejection with "حجم الملف يتجاوز الحد الأقصى (10 ميغابايت)"
- Network failure: retry button on the card; do not roll back successfully uploaded sibling files
- Locked state mid-upload: backend will respond with `WORKFLOW_LOCKED_STATE` (HTTP 403); the page shows a banner and disables further uploads

---

**Step 4 — Review & Submit:**

Read-only summary panel arranged in three sections, each with an "edit" link that jumps the user back to that step:

- **Request Data section** — Import Type, Importer, Amount, Payment Terms
- **Supplier & Shipment section** — Supplier, Invoice Number, Arrival Port, Origin Country
- **Uploaded Documents section** — list of all required document slots with their upload status badge ("تم الرفع" / "إلزامي" / "اختياري")

Declaration block at the bottom:

- Shield-check icon (primary blue)
- Title: "إقرار وتعهد"
- Text confirming the accuracy of submitted data and authority to submit on behalf of the bank
- Checkbox required to enable the Submit button

---

**Navigation footer (always present):**

- "← السابق" (outline; disabled on step 1)
- "حفظ كمسودة" (outline) — persists the request as `DRAFT` without submitting; safe to invoke at any step
- "التالي ←" (primary; steps 1-3)
- "إرسال للمراجعة" (primary; step 4 only) — submits the request, transitions to `SUBMITTED`, moves owner role to `BANK_REVIEWER`, and routes the user back to `/requests` with a success toast

**Disabled-submit reasons** must be surfaced inline (tooltip on the disabled button), e.g. "أكمل رفع المستندات الإلزامية أولاً" or "وافق على الإقرار أولاً".

---

### Edit Request (`/requests/[id]/edit`)

Reuses the 4-step wizard with all fields pre-filled. Available only when the request status ∈ {`DRAFT`, `DRAFT_REJECTED_INTERNAL`, `BANK_RETURNED`, `SUPPORT_RETURNED`}.

**Correction context:** the CorrectionBanner is mounted at the top of the wizard, pinned visibly across all four steps so the user does not lose sight of the return reason. The banner includes:

- Reason text from the returning party (BANK_REVIEWER / SUPPORT_COMMITTEE)
- Returning user's name and role chip
- Return timestamp
- Optional sub-list of specific fields/documents flagged by the returner if provided

**Behavior on save:** transitions to `DRAFT` if invoked before submit.
**Behavior on submit:** transitions to `SUBMITTED`; the request is queued back into the bank reviewer's queue (the reviewer is allowed to be different from the one who returned it).

If the request status changes server-side while the wizard is open (e.g. another bank user submits or a reviewer locks it), the page receives a 403 / 409 from the next save attempt, freezes the form, and surfaces a banner explaining the conflict with a "تحديث الصفحة" reload action.

---

### Request Detail (`/requests/[id]`)

The detail page is the read-and-track surface for DATA_ENTRY after submission, and the launchpad for correction work when the request comes back.

**Page header:**

- Title: Request reference number (monospace)
- Breadcrumbs: الرئيسية → طلبات التمويل → [Reference]
- Status badge (colored, using the simplified business label)
- Print button → `/requests/[id]/print`
- Edit button (conditional, only when status ∈ editable set above) → `/requests/[id]/edit`

---

**Context banners (rendered at the top, mutually exclusive in priority order):**

1. **CorrectionBanner** (amber) — appears when status ∈ {`BANK_RETURNED`, `SUPPORT_RETURNED`, `DRAFT_REJECTED_INTERNAL`}. Shows:
   - Returning party name + role chip
   - Return reason text
   - Return timestamp
   - Prominent "تعديل وإعادة الإرسال" primary button → `/requests/[id]/edit`

2. **LockedBanner** (gray) — appears for terminal or non-editable states (`COMPLETED`, `CUSTOMS_DECLARATION_ISSUED`, `BANK_REJECTED`, `SUPPORT_REJECTED`, `EXECUTIVE_REJECTED`). Shows a Lock icon, a "مقفل" pill, and a sentence explaining that no further edits are possible. For terminal rejections, the banner uses a stronger red-tinted variant to distinguish "irreversible" from "merely locked".

DATA_ENTRY does not see ActiveReviewBanner, ClaimedByOthersBanner, or VotingPanel — those belong to other roles.

---

**WorkflowProgress:**

A horizontal progress component spanning the full lifecycle. The bar uses business-language stage labels rather than raw enums:

`مسودة → مقدّم → مراجعة البنك → معتمد من البنك → قيد المساندة → معتمد من المساندة → تصويت تنفيذي → قرار تنفيذي → سويفت → تأكيد المصارفة → مكتمل`

The current stage is highlighted in primary blue. Completed stages are green checkmarks. Future stages are muted gray. If the request is in a return loop (`BANK_RETURNED`, `SUPPORT_RETURNED`), the bar renders the loop visually (the active marker steps back to "مسودة" with an amber arrow indicating the loop).

---

**Tabs:**

| Tab            | Purpose                                                                                |
| -------------- | -------------------------------------------------------------------------------------- |
| المعلومات      | Request and supplier/shipment fields in grouped read-only sections                     |
| الوثائق        | Document checklist scoped to documents this role may download                          |
| الأطراف        | Actors who have touched the request so far (name + role chip + timestamp)              |
| السجل          | Lightweight business-language timeline of meaningful transitions                       |

DATA_ENTRY does not see Audit Trail, Voting, or FX Confirmation tabs — these are governance surfaces.

---

**Overview tab (المعلومات):**

Grouped read-only sections: Request Data (Reference, Merchant, Goods, Amount & Currency, Payment Terms, Notes), Supplier & Shipment (Supplier, Origin Country, Invoice number/date, Shipping Port, Arrival Port, Bill of Lading, Customs Office). All fields are read-only on this tab; edits happen only via the wizard.

---

**Documents tab (الوثائق):**

DocumentChecklist showing every required and optional document slot for this request. Each row shows:

- Document name + Required/Optional pill
- File name + upload timestamp + uploader name (own bank only)
- Download button **only** for the documents DATA_ENTRY may download (intake-stage request documents). The SWIFT document, FX confirmation request, and external FX confirmation PDF are shown as locked rows with an explanation tooltip — never as forbidden errors, never as hidden rows (the user should know they exist; they just cannot download them).

If the request is in `DRAFT`, `BANK_RETURNED`, `SUPPORT_RETURNED`, or `DRAFT_REJECTED_INTERNAL`, a "تحديث المستندات" button takes the user to step 3 of the wizard.

---

**Parties tab (الأطراف):**

Lists actors as they accumulate across the lifecycle. Each entry:

- Avatar (initials) + full name
- Role chip
- Action performed (submitted, approved, returned, …)
- Timestamp

DATA_ENTRY sees actor names appropriate to its scope; CBY-side reviewers are presented by role and committee identity where backend policy allows.

---

**Activity log tab (السجل):**

A compact, business-language event list (not a raw audit dump). Entries are limited to events the requester needs to know about:

- "تم إنشاء الطلب"
- "تم الإرسال للمراجعة"
- "أُعيد الطلب للتعديل من [reviewer name] — السبب: [reason]"
- "تم اعتماد الطلب من البنك"
- "تم اعتماد الطلب من لجنة المساندة"
- "صدر القرار التنفيذي: معتمد / مرفوض"
- "تم إصدار تأكيد المصارفة"
- "اكتمل الطلب"

Raw enum transitions are not exposed here. The Audit page is a separate governance surface for CBY roles.

---

**ActionsPanel (right column):**

Compact, condensed to what is actionable now:

- If status ∈ editable set: "تعديل الطلب" (primary) + "حفظ كمسودة" link
- If status = `DRAFT`: "إرسال للمراجعة" (primary) — sends to bank review
- If status = `BANK_RETURNED` / `SUPPORT_RETURNED` / `DRAFT_REJECTED_INTERNAL`: "تعديل وإعادة الإرسال" (primary) jumps to wizard step that the returner flagged
- If status is in flight at CBY: a single read-only sentence "الطلب قيد المعالجة لدى البنك المركزي. سيتم إعلامك بأي إجراء يخصك." with no buttons
- If status is terminal: empty panel + the LockedBanner already at the top

The ActionsPanel must not show review/approve/reject/vote/claim/SWIFT/FX buttons for any reason.

---

### Print Request (`/requests/[id]/print`)

A print-optimized full-page A4 layout with: CBY letterhead, request reference and metadata, full request data, supplier/shipment, document checklist, and a stage timeline. No sidebar or app chrome is rendered. The browser print dialog is triggered automatically ~300 ms after load. Page rendering is RTL with Cairo/IBM Plex Sans Arabic. Page numbers and a generation timestamp appear in the footer.

---

### Notifications (`/notifications`)

DATA_ENTRY receives notifications only for events that affect their requests or assigned work:

- Request returned for correction (`BANK_RETURNED`, `SUPPORT_RETURNED`, `DRAFT_REJECTED_INTERNAL`) — highest visual priority
- Request approved by bank
- Request approved/rejected by support
- Final executive decision
- Request completed / external FX confirmation issued
- Session inactivity warning (if browser session approaches the inactivity threshold)

Voting tallies, claim transfers, audit alerts, and SLA escalations are not delivered to this role.

---

**Page header:**

- Title: "مركز الإشعارات"
- Subtitle: "[X] غير مقروء من [Y] إجمالاً"
- "تعليم الكل كمقروء" button (disabled when all are read)

---

**Tab filter bar:**

- الكل (All) — total count badge
- غير مقروء (Unread) — unread count badge
- مقروء (Read) — read count badge

---

**Toolbar:**

- Search input: "ابحث في الإشعارات..."
- When rows are selected: bulk toolbar with Mark Read, Export, Print, and Clear Selection buttons

---

**Data table:**

| Column     | Description                                                                                            |
| ---------- | ------------------------------------------------------------------------------------------------------ |
| (checkbox) | Bulk selection                                                                                         |
| Type       | Colored icon + label: عاجل (rose), مهم (amber), إنجاز (emerald), إشعار (sky)                          |
| Message    | Notification body; bold if unread; second line shows linked request reference in monospace             |
| Date       | Relative timestamp + absolute date on hover                                                            |
| Status     | "مقروء" (outline) or blue dot + "غير مقروء" (primary)                                                  |
| Actions    | Three-dot dropdown: Open Request (if linked), Mark Read/Unread                                         |

Unread rows have a faint primary-tinted row background.
Clicking a row marks it read and navigates to the linked request if applicable.

**Empty states:**

- No notifications at all: inbox icon + "لا توجد إشعارات بعد" + reassurance line "ستظهر هنا تنبيهات الطلبات والقرارات."
- No matches for filter: search icon + "لا توجد إشعارات مطابقة" + "مسح الفلتر" link

**Pagination footer:** rows per page selector (10/20/30/40/50), Page X of Y, First/Prev/Next/Last.

---

### Settings (`/settings`)

**6-tab layout:**

1. **General** — display language, date format, layout mode (boxed / full-width)
2. **Security** — change password (current + new + confirm), session-revoke "تسجيل خروج من كل الأجهزة"
3. **Notifications** — per-event toggles for in-app notification preferences (Request Returned, Approved, Completed, Final Decision)
4. **MFA** — enable / disable multi-factor authentication, recovery codes
5. **Demo** — demo/test mode (visible only if backend exposes it; otherwise hidden)
6. **Appearance** — theme (light / dark), font size

Each tab has its own "حفظ التعديلات" button at the bottom. Unsaved changes show a sticky bottom bar with Save/Discard actions. Sensitive changes (password, MFA) require re-authentication.

---

### Profile (`/profile`)

Accessible from the sidebar footer dropdown.

**3-column layout:**

- **Left card:** avatar circle (initials, large), full name, email, role chip, bank affiliation, small stats (total requests, active requests)
- **Center section:** editable personal info form — Name (editable), Email (read-only), Role (read-only), Phone (editable, optional), Preferred Language
- **Right card:** Security & MFA — MFA status, last password change, recent logins (last 5), "تسجيل خروج من جميع الأجهزة"

A single "حفظ التعديلات" primary button at the bottom of the form. Sensitive changes audit-log the actor and the change.

---

## Forbidden Actions Reference

DATA_ENTRY cannot, under any UI condition:

- Approve, reject, or return any request (bank-side or CBY-side)
- Review a request (including their own)
- Claim or release a support review
- Cast or modify an executive vote
- Open, close, or finalize a voting session
- Upload SWIFT, FX confirmation request, or external FX confirmation documents
- Download SWIFT, FX confirmation request, or external FX confirmation documents
- Access other banks' requests, merchants, or staff
- View CBY internal audit, voting tallies, or claim ownership data
- Manage merchants, staff, or any bank-admin surface (those belong to `BANK_ADMIN`)

The UI must not render any of the above controls. If a backend response erroneously offers such an action, frontend should drop it rather than display it (defense-in-depth).

---

## Cross-Role Handoffs

DATA_ENTRY participates in two handoff points; both must feel clean and unambiguous:

1. **DATA_ENTRY → BANK_REVIEWER (on submit):** the request leaves the user's editable set, status changes to `SUBMITTED`, the LockedBanner appears as soon as the bank reviewer starts review (`BANK_REVIEW`). DATA_ENTRY should be notified when the reviewer acts.
2. **BANK_REVIEWER → DATA_ENTRY (on return) / SUPPORT_COMMITTEE → DATA_ENTRY (on return):** the CorrectionBanner becomes the primary surface; the user is notified immediately; the wizard reopens at the returner-flagged step where possible.

The Action-required strip on the dashboard exists specifically to make these handoffs visible.

---

## UX Principles

- The dashboard is a task launcher, not an analytics console.
- Correction work is always more important than aggregate metrics — surface it first.
- Status labels stay business-friendly. Raw CBY enum names do not belong here.
- Every status the user sees should be either "actionable by me" or "informational tracking". Never expose claim/voting/SWIFT internals.
- Document upload errors must be inline, specific, and recoverable; never silent.
- The wizard must save drafts aggressively (per step) and never lose user input on network blips.
- Returns must feel like correction guidance, not punishment. The CorrectionBanner shows the reason in plain language and offers a direct edit button.
- Read-only states must visually feel read-only — no disabled-looking buttons that suggest the user is missing permission they should have.
- Loading uses skeletons, not spinners. Empty states explain what to do next or hide entirely if no action is possible.
- This role is bank-scoped; the UI must never imply cross-bank visibility, even accidentally (no bank filter, no other-bank entries in search suggestions, no other-bank merchants).

# SWIFT_OFFICER — Bank SWIFT Officer

Arabic label: موظف السويفت بالبنك

---

## Role Identity

The bank-side specialist responsible for the **post-executive-approval document handoff**. After the Executive Committee approves a request, the workflow auto-transitions into `WAITING_FOR_SWIFT`. From that moment, this role is the operational owner of the request inside the bank until both required documents — the SWIFT proof and the FX confirmation request — are uploaded.

SWIFT_OFFICER is a **task-focused operational role with a strict two-document completion gate**. The role does not review, approve, vote, claim, or finalize anything. Its responsibility is narrow but high-leverage: every approved request stalls here until SWIFT documentation is provided, and any delay on this role directly delays the Director's external FX confirmation completion.

---

## Operational Posture

| Aspect              | Tone                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------- |
| Work mode           | Operational / specialized upload work                                                 |
| Primary surface     | SWIFT queue (`WAITING_FOR_SWIFT`) + per-request upload page                           |
| Secondary surface   | Read-only tracking of completed SWIFT-stage requests through to completion            |
| Status language     | Full canonical labels — the officer needs precise stage visibility                    |
| Visual density      | Low to medium; the upload page is intentionally distraction-free                      |
| Decision tone       | Procedural — file integrity, two-document gate, locked-data acknowledgment            |

---

## Scope & Boundary

- **Organization scope:** Own bank only. The SWIFT officer does not see other banks' SWIFT queues.
- **Stage scope:** Primarily `WAITING_FOR_SWIFT` and `SWIFT_UPLOADED`. Earlier stages are read-only context (this officer should understand which request is approaching them via the workflow). Later stages (`FX_CONFIRMATION_PENDING`, `COMPLETED`) are read-only tracking.
- **Document scope:** Uploads two documents — SWIFT PDF and FX confirmation request PDF — and downloads the FX confirmation request template. No other upload authority.
- **Data scope:** Request business data is **locked** for editing once it enters `WAITING_FOR_SWIFT`. The SWIFT officer can read all request data but cannot modify any business field. This is enforced both at backend (mutation returns `WORKFLOW_LOCKED_STATE`, HTTP 403) and at the UI layer (no editable fields surface).

---

## Workflow Authority Summary

| Stage                              | SWIFT_OFFICER authority                                                |
| ---------------------------------- | ---------------------------------------------------------------------- |
| `DRAFT` through `EXECUTIVE_VOTING_CLOSED` | Read-only — no decision or upload action                        |
| `EXECUTIVE_APPROVED`               | Read-only context; awaiting auto-transition to `WAITING_FOR_SWIFT`     |
| `WAITING_FOR_SWIFT`                | Download FX template; Upload SWIFT PDF; Upload FX request PDF; Submit SWIFT stage (requires both PDFs) |
| `SWIFT_UPLOADED`                   | Read-only; uploaded files visible; further uploads blocked              |
| `FX_CONFIRMATION_PENDING`          | Read-only tracking — Director is now the owner                          |
| `COMPLETED`                        | Read-only; final state                                                  |
| All rejection/terminal states      | Read-only                                                              |

The two-document gate is critical: the backend will not advance the request to `SWIFT_UPLOADED` until **both** PDFs are uploaded. The UI must mirror this — the "Submit SWIFT Stage" button stays disabled with a clear inline reason until both file slots are populated.

---

## Document Authority

| Document                            | Access                |
| ----------------------------------- | --------------------- |
| Request documents (intake)          | View + Download / Own bank |
| FX confirmation request template    | Download (system-provided template) |
| SWIFT document                      | Upload + View + Download / Own bank |
| FX confirmation request document    | Upload + View + Download / Own bank |
| External FX confirmation PDF        | No                    |

The external FX confirmation PDF is generated and completed by the Committee Director. SWIFT_OFFICER hands off cleanly at `SWIFT_UPLOADED` and does not see the Director's downstream document.

---

## Sidebar Navigation

| Group            | Item                          | Route          |
| ---------------- | ----------------------------- | -------------- |
| الرئيسية (Main)  | اللوحة الرئيسية (Dashboard)   | /dashboard     |
| الرئيسية         | طلبات التمويل (Requests)      | /requests      |
| الرئيسية         | الإشعارات (Notifications)     | /notifications |
| الأخرى (Other)   | الإعدادات (Settings)          | /settings      |

Operations and Administration groups are intentionally absent. The role's nav is the most minimal of all roles — by design.

---

## Pages

### Login (`/login`)

Identical to the login page in `data-entry.md`.

---

### Dashboard (`/dashboard`)

The SWIFT Officer dashboard is a **focused upload work queue**. Its job is to answer two questions in less than three seconds:

1. How many requests are waiting for SWIFT upload right now?
2. What is my recent throughput / outstanding load?

Nothing else belongs here. No bank-wide analytics, no cross-bank queues, no review controls, no voting tallies. The role is narrow and the dashboard should reflect that.

---

**Page header:**

- Greeting: "أهلاً، [first name]"
- Subtitle: "موظف السويفت بـ[bank name]"
- No primary action button (uploads happen per-request, not from the dashboard)

---

**Action-required strip (conditional, highest priority):**

When `pending_swift_upload > 0`, a strip appears beneath the header:

- Icon: AlertTriangle (amber)
- Title: "[N] طلبات بانتظار رفع وثائق السويفت"
- Subtitle: shows the oldest waiting request reference + how long it has been waiting
- Action: "ابدأ الرفع" → `/requests?tab=pending_swift` (focused on the oldest)

This strip is the highest-attention element. SLA on this stage matters because Director's external FX confirmation work is downstream of it.

---

**KPI cards (4-column grid):**

| Card                                       | Source                | Color                                | Click-through                            |
| ------------------------------------------ | --------------------- | ------------------------------------ | ---------------------------------------- |
| بانتظار رفع السويفت (Pending SWIFT Upload) | `pending_swift_upload`| Amber (left-border highlight when > 0) | `/requests?tab=pending_swift`          |
| تم رفع السويفت (SWIFT Uploaded)            | `uploaded`            | Cyan (info)                          | `/requests?tab=swift_done`               |
| مكتمل (Completed)                          | `final_approved`      | Green                                | `/requests?tab=completed`                |
| رُفض من اللجنة (Executive Rejected)        | `final_rejected`      | Rose                                 | `/requests?tab=rejected`                 |

The "rejected" KPI is informational — these requests never enter the SWIFT queue, but the officer should see them to understand portfolio outcomes.

---

**SWIFT queue table (the heart of this page):**

Shows requests currently in `WAITING_FOR_SWIFT` or recently transitioned to `SWIFT_UPLOADED`, sorted by age in stage (oldest first). The queue is the officer's actual work surface.

| Column                              | Description                                                                       |
| ----------------------------------- | --------------------------------------------------------------------------------- |
| Reference Number                    | Monospace                                                                         |
| Merchant                            | Trade name                                                                        |
| Amount                              | Formatted amount + currency, right-aligned                                        |
| Status                              | Status badge (cyan for SWIFT stages)                                              |
| Age in `WAITING_FOR_SWIFT`          | Time since auto-transition from `EXECUTIVE_APPROVED`; turns amber after configured warning threshold |
| Documents progress                  | Two pill indicators: "السويفت" + "طلب تأكيد المصارفة" — green when uploaded, gray otherwise |
| Actions                             | Primary: "رفع وثائق السويفت" (links to `/requests/[id]/swift`); plus a "تحميل النموذج" inline link |

The two-pill document indicator makes the upload-progress state visible at-a-glance from the queue, without opening each request.

**Empty states:**

- No pending uploads: reassuring illustration + "لا توجد طلبات بانتظار رفع السويفت حالياً ✓" (healthy state, not a problem to solve)
- Loading: skeleton rows
- Error: inline error with retry

---

### Requests List (`/requests`)

**Page header:**

- Title: "طلبات تمويل الواردات"
- Subtitle: "طلبات جهتك المتعلقة بمرحلة السويفت وتأكيد المصارفة"
- Breadcrumbs: الرئيسية → طلبات التمويل

---

**Stage tabs (focused on SWIFT-relevant statuses):**

| Tab key         | Label                       | Mapped statuses                                                       |
| --------------- | --------------------------- | --------------------------------------------------------------------- |
| `pending_swift` | بانتظار رفع السويفت         | `EXECUTIVE_APPROVED`, `WAITING_FOR_SWIFT`                             |
| `swift_done`    | تم رفع السويفت              | `SWIFT_UPLOADED`, `FX_CONFIRMATION_PENDING`                           |
| `completed`     | مكتمل                       | `CUSTOMS_DECLARATION_ISSUED`, `COMPLETED`                             |
| `rejected`      | رُفض قبل السويفت            | `EXECUTIVE_REJECTED`, `SUPPORT_REJECTED`, `BANK_REJECTED`             |
| `all`           | الكل                        | All own-bank requests                                                 |

The `pending_swift` tab is intentionally first.

---

**Toolbar:**

- Search input (reference, merchant, supplier, invoice number)
- Customize Columns dropdown
- Export button (CSV; filter-scoped)
- Refresh button

No bulk-upload action. Each request's SWIFT upload is per-request because each requires SWIFT-reference data entry plus two distinct PDF uploads. Bulk-upload would obscure the two-document gate.

---

**Data table:**

| Column           | Description                                                            |
| ---------------- | ---------------------------------------------------------------------- |
| Reference Number | Monospace                                                              |
| Merchant         | Trade name                                                             |
| Amount           | Formatted amount + currency, right-aligned                             |
| Status           | Full canonical status badge                                            |
| Documents        | Two-pill indicator (السويفت / طلب تأكيد المصارفة)                      |
| Age in Stage     | Time since last transition                                             |
| Actions          | "رفع" (primary cyan) if `WAITING_FOR_SWIFT`; "عرض" otherwise           |

Row click navigates to `/requests/[id]`.

---

### Request Detail (`/requests/[id]`)

The detail page is the read-and-context surface before or after the SWIFT upload action. The primary upload work happens on the dedicated `/requests/[id]/swift` page; the detail page surfaces the request data, the document checklist, and a clear shortcut to the upload page when applicable.

**Context banners:**

1. **PreApprovalLockedBanner** (gray) — appears for any request not yet at `EXECUTIVE_APPROVED`. SWIFT_OFFICER can read but cannot upload. Banner explains: "هذا الطلب لم يصل بعد مرحلة السويفت. لا يمكن رفع الوثائق حتى يكتمل اعتماد اللجنة التنفيذية."
2. **SwiftReadyBanner** (cyan) — appears for `WAITING_FOR_SWIFT`. Shows: "الطلب جاهز لرفع وثائق السويفت" with a primary "ابدأ الرفع" button → `/requests/[id]/swift`.
3. **SwiftCompletedBanner** (gray) — appears for `SWIFT_UPLOADED` and `FX_CONFIRMATION_PENDING`. Shows: "تم تسليم السويفت — انتقلت المسؤولية إلى مدير اللجنة التنفيذية لإتمام تأكيد المصارفة الخارجية." No further upload action available.
4. **LockedBanner** (gray; red variant for terminal rejection) — appears for terminal states.

No CorrectionBanner, no claim banners, no voting panel — those are other roles' surfaces.

---

**WorkflowProgress:** standard horizontal stage progress bar; current stage in primary blue.

---

**Tabs:**

| Tab            | Purpose                                                                  |
| -------------- | ------------------------------------------------------------------------ |
| المعلومات      | Request and supplier/shipment fields, read-only                          |
| الوثائق        | DocumentChecklist with the role's accessible documents and upload shortcut |
| الأطراف        | Actors so far                                                            |
| السجل          | Business-readable stage history                                          |

---

**Overview tab:** read-only request data. No editable fields. The SWIFT officer must not alter business data.

**Documents tab:** DocumentChecklist showing every document slot. SWIFT_OFFICER's accessible documents (request, SWIFT, FX request) show with Download. The external FX confirmation PDF row is shown as locked with a tooltip ("مخصص لمدير اللجنة التنفيذية."). An "Upload SWIFT Documents" button appears at the top of the Documents tab when status = `WAITING_FOR_SWIFT`, routing to the dedicated upload page.

**Parties tab:** all actors so far (Submitter, Bank Reviewer, Support claimant, Executive members, Committee Director).

**Activity log tab:** stage history. Auto-transitions are labeled (e.g. "تم نقل الطلب تلقائياً إلى مرحلة السويفت بعد اعتماد اللجنة التنفيذية").

---

**ActionsPanel (right column):**

- Status = `WAITING_FOR_SWIFT`: "رفع وثائق السويفت" (primary cyan) → `/requests/[id]/swift`
- Status = `EXECUTIVE_APPROVED` (transient): "في انتظار الإتاحة" (disabled, gray) with explanatory tooltip
- Status = `SWIFT_UPLOADED` / `FX_CONFIRMATION_PENDING`: informational sentence ("تم تسليم السويفت — المسؤولية الآن مع مدير اللجنة التنفيذية.") with no action button
- Other statuses: empty panel; LockedBanner at top provides context

---

### SWIFT Upload (`/requests/[id]/swift`)

The dedicated upload page is the heart of this role. It is intentionally distraction-free: no sidebar widgets, no analytics, no quick actions, no unrelated banners — only the upload work.

Accessible to `SWIFT_OFFICER` when the request status ∈ {`WAITING_FOR_SWIFT`, `SWIFT_UPLOADED`}. For any other state or any other role, the page renders a locked / access-denied state instead of the upload form. Direct URL access is gated server-side; the UI is defense-in-depth.

---

**Page header:**

- Title: "إرفاق وثائق السويفت — [reference]"
- Subtitle: "بيانات الطلب مقفلة. مسموح فقط برفع وثيقة السويفت وطلب تأكيد المصارفة."
- Breadcrumbs: الرئيسية → طلبات التمويل → [Reference] → السويفت
- Current status badge in the header actions area

---

**Locked-data summary panel (left side, persistent):**

A read-only summary card showing the key request facts the SWIFT officer needs while preparing the upload — reference, merchant, supplier, amount + currency, payment terms, invoice number/date, arrival port, bill of lading. Each field is rendered with a lock icon next to its label to reinforce that the data is non-editable.

Below the summary: a "عرض كامل بيانات الطلب" link that opens the full request detail in a new tab if the officer needs deeper context.

---

**Upload form (right side, primary work surface):**

Three sections in order:

**Section 1 — SWIFT Reference Number**

- Label: "رقم مرجع السويفت (UETR / Message Reference)"
- Single text input, required
- Inline validation: format checked against expected SWIFT reference patterns; soft warning (not hard block) if pattern mismatch — backend remains authoritative
- Helper text explaining what the bank's SWIFT system produces as the reference

**Section 2 — SWIFT Document Upload**

- Title: "وثيقة السويفت"
- Helper: "نسخة PDF من رسالة MT103 / MT202 الصادرة من نظام السويفت."
- Dashed-border drop zone with Upload icon and "اسحب الملف هنا أو اضغط للاختيار"
- "اختر ملف" button (outline) — opens file picker, PDF only, max 10 MB
- After upload: file name, file size, SHA-256 "تم التحقق" badge, Preview button, Remove button
- Inline error handling: non-PDF → "صيغة الملف غير مدعومة"; > 10 MB → "حجم الملف يتجاوز الحد الأقصى"; network failure → retry with file retained client-side

**Section 3 — FX Confirmation Request Document**

- Title: "طلب تأكيد المصارفة الخارجية"
- Helper: "النموذج الرسمي المعبأ والمختوم من البنك، يُرفع كملف PDF."
- Template download button at the top of the section: "تحميل النموذج الفارغ" — downloads the official `FX_CONFIRMATION_REQUEST_TEMPLATE` PDF directly from system templates (versioned; the active version is governed by CBY Admin's Document Rules surface)
- Identical drop zone + file picker as Section 2 (PDF only, max 10 MB)
- Same upload UX (preview, remove, error handling)

---

**Submit panel (bottom of the form):**

- Primary button: "تسليم وثائق السويفت" (primary cyan, large)
- Disabled until: SWIFT reference number is provided AND both PDFs are uploaded
- Tooltip on disabled state explains the missing requirement specifically: "أكمل رفع وثيقة السويفت قبل التسليم" / "أكمل رفع طلب تأكيد المصارفة قبل التسليم" / "أدخل رقم مرجع السويفت أولاً"
- "إلغاء والعودة للطلب" link below the primary button (outline)

On successful submission:

- Backend transitions the request to `SWIFT_UPLOADED`
- The auto-chain moves to `FX_CONFIRMATION_PENDING`
- The page transitions to a confirmation state: green success card with "تم تسليم وثائق السويفت بنجاح" + a "العودة إلى الطابور" primary button
- The Director and other downstream roles receive notifications per their preferences

If the submission fails (network error, backend validation error, race condition with another officer or with a status change), the form remains intact; an inline error banner explains the failure with a retry action. Uploaded files are preserved client-side.

---

**Race conditions and concurrent edits:**

If the request status changes while the officer is on the upload page (e.g. a CBY admin re-routes the request, or another SWIFT officer in the same bank submits first), the next interaction will receive a `WORKFLOW_LOCKED_STATE` 403. The page surfaces a banner explaining the state change with a "تحديث الصفحة" reload action — never silently discarding the user's in-progress upload work.

---

**Access-denied state (wrong role or wrong status):**

Instead of the form, the page renders:

- Lock icon
- Title: "غير متاح حالياً" / "لا تملك صلاحية الوصول"
- Explanation specific to the case (wrong status: "هذا الطلب ليس في مرحلة السويفت."; wrong role: "هذه الصفحة مخصصة لموظفي السويفت بالبنك.")
- "العودة" button to the request detail or dashboard

---

### Notifications (`/notifications`)

The SWIFT Officer receives notifications for:

- Request approved by Executive Committee and now `WAITING_FOR_SWIFT` (own bank) — highest priority
- SWIFT submission confirmed (own bank)
- FX confirmation completed by Director (own bank) — informational; closes the loop on a recently-submitted SWIFT case
- Template version update for the FX confirmation request (operational change announcement)

Voting tallies, claim transfers, audit alerts, other-bank events, and intake-stage events are not delivered to this role.

Page structure is identical to the notifications page in `data-entry.md`.

---

### Settings (`/settings`)

Same 6-tab layout as `data-entry.md`. Notification preferences default to all SWIFT-relevant events enabled.

---

### Profile (`/profile`)

Same 3-column layout as `data-entry.md`. Center stats highlight: total SWIFT uploads, average time-to-upload after executive approval (own performance).

---

## Forbidden Actions Reference

SWIFT_OFFICER cannot, under any UI condition:

- Create, edit, or delete request business data
- Review, approve, return, or reject requests at bank stage
- Claim or release support reviews
- Cast, modify, or finalize executive votes
- Generate or upload the signed external FX confirmation PDF
- Download the signed external FX confirmation PDF (Director's territory)
- Bulk-upload SWIFT documents across multiple requests
- Skip the two-document gate (submit SWIFT stage without both PDFs)
- Modify request data on the upload page (all fields are read-only with lock icons)
- See other banks' SWIFT queues or requests
- Manage merchants, staff, or any administrative surface

The UI must not render any of these controls. If a backend response erroneously offers such an action, frontend drops it defensively.

---

## Cross-Role Handoffs

SWIFT_OFFICER sits at the **executive-approval-to-completion boundary**. Their work is the last bank-side step:

1. **Executive Committee Director → SWIFT_OFFICER (auto on `EXECUTIVE_APPROVED`):** The auto-chain transitions the request to `WAITING_FOR_SWIFT`. The officer receives a notification and the request appears in the queue.
2. **SWIFT_OFFICER → COMMITTEE_DIRECTOR (on SWIFT submission):** Both PDFs uploaded → status becomes `SWIFT_UPLOADED` → auto-chain to `FX_CONFIRMATION_PENDING`. The Director can now download the generated external FX confirmation PDF and proceed with the external sign/stamp workflow.

These are the only handoff points for this role. The narrowness is intentional.

---

## UX Principles

- The role is narrow and the UI should feel narrow. No analytics dashboards, no broad nav, no cross-stage controls.
- The two-document gate is non-negotiable. The submit button must stay disabled with a specific, actionable reason until both PDFs and the SWIFT reference are provided.
- The upload page is distraction-free. The locked-data summary is informational only; no editable fields appear anywhere on this page.
- Lock icons on the data summary explicitly signal that business data is non-editable. The officer should never wonder "can I change this?" — the answer is visually clear.
- The two-pill document indicator on the queue makes upload progress visible without opening each request.
- The action-required strip on the dashboard is the highest-attention surface because every hour of delay here directly delays the Director.
- Confirmation after submission is explicit and celebratory — "تم تسليم وثائق السويفت بنجاح" — because successful upload is the role's primary success metric.
- The template download is always one click away on the upload page; the officer should never have to hunt for the FX request template.
- Access-denied states explain *why* the page is blocked (wrong status / wrong role), not just *that* it is blocked.
- The role's notifications are operationally relevant only — no general workflow noise.

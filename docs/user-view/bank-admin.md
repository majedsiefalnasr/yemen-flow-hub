# BANK_ADMIN — Bank Administrator

Arabic label: مسؤول البنك

---

## Role Identity

The bank-level operational administrator. The Bank Admin runs the bank's identity and master-data surfaces (staff accounts, merchant registry, bank reports), oversees the bank's full request portfolio for managerial visibility, and supports operational continuity through draft-creation fallback. The role is responsible for **administration**, not workflow governance.

BANK_ADMIN is **not a bank approval authority**. This role does not review, approve, return, or reject requests, does not vote, does not claim support reviews, and does not perform SWIFT uploads. The segregation-of-duties separation between requester (`DATA_ENTRY`) and reviewer (`BANK_REVIEWER`) must remain intact and visible — Bank Admin must never operate as a fallback approver.

The role can create and prepare draft requests as an operational fallback (when DATA_ENTRY is unavailable) per current implementation, but this is not its primary responsibility and is not in the primary navigation.

---

## Operational Posture

| Aspect              | Tone                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------- |
| Work mode           | Administrative / oversight-light (managerial visibility)                              |
| Primary surface     | Staff management + merchant registry + bank reports                                   |
| Secondary surface   | Read-only bank portfolio view                                                         |
| Tertiary surface    | Operational draft-creation fallback                                                   |
| Status language     | Full canonical CBY labels for portfolio visibility                                    |
| Visual density      | Medium: master-data tables, analytics, staff/merchant CRUD                            |
| Decision tone       | Administrative — staff/merchant lifecycle; governance-light                           |

---

## Scope & Boundary

- **Organization scope:** Own bank only. Cross-bank visibility is forbidden in any UI surface (no other-bank staff, no other-bank merchants, no other-bank requests).
- **User scope:** Own-bank staff only. Bank-manageable roles only — `DATA_ENTRY` and `BANK_REVIEWER`. CBY roles (`SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `CBY_ADMIN`) and the post-approval bank role (`SWIFT_OFFICER`) must not be assignable from this surface. (`SWIFT_OFFICER` is managed by CBY Admin via the system staff page, since the SWIFT function is bank-side but its provisioning is governed centrally.)
- **Request scope:** Read-only managerial view across the bank's full portfolio. Draft creation is allowed as an operational fallback only.
- **Document scope:** Same as Bank Reviewer for bank-controlled documents (request, SWIFT, FX request); no access to the generated/signed external FX confirmation PDF.

---

## Workflow Authority Summary

| Stage                            | BANK_ADMIN authority                                                            |
| -------------------------------- | ------------------------------------------------------------------------------- |
| `DRAFT`                          | Create, edit, attach documents, submit (operational fallback only)              |
| `SUBMITTED` through `COMPLETED`  | Read-only portfolio view                                                        |
| `BANK_RETURNED` / `SUPPORT_RETURNED` / `DRAFT_REJECTED_INTERNAL` | Cannot edit on behalf of the requester (those belong to DATA_ENTRY) |
| All decision stages              | No approval/review/return/reject/vote/claim/SWIFT/FX authority                   |

Even though the Bank Admin can create drafts as a fallback, **the requester identity matters** — backend records the actual user as the request creator. If a Bank Admin creates and submits a draft, they cannot then be the reviewer for that request (segregation of duties applies to any user who created the request, regardless of role).

---

## Document Authority

| Document                            | Access                |
| ----------------------------------- | --------------------- |
| Request documents (intake)          | View + Download / Own bank |
| SWIFT document                      | View + Download / Own bank |
| FX confirmation request document    | View + Download / Own bank |
| External FX confirmation PDF        | No                    |

The external FX confirmation PDF is intentionally restricted: it is a governance artifact owned by the Committee Director and the Bank Reviewer (as the bank-side official record). Bank Admin does not need it for administrative functions.

---

## Sidebar Navigation

| Group                    | Item                          | Route        |
| ------------------------ | ----------------------------- | ------------ |
| الرئيسية (Main)          | اللوحة الرئيسية (Dashboard)   | /dashboard   |
| الرئيسية                 | طلبات التمويل (Requests)      | /requests    |
| الرئيسية                 | الإشعارات (Notifications)     | /notifications |
| الإدارة (Administration) | إدارة المستوردين (Importers)  | /merchants   |
| الإدارة                  | الموظفون (Staff)              | /staff       |
| الإدارة                  | التقارير والتحليلات (Reports) | /reports     |
| الأخرى (Other)           | الإعدادات (Settings)          | /settings    |

The Operations group (New Request) is intentionally absent from the primary nav even though `/requests/new` is reachable as an operational fallback. The administrative grouping signals the role's nature.

---

## Pages

### Login (`/login`)

Identical to the login page in `data-entry.md`.

---

### Dashboard (`/dashboard`)

The Bank Admin dashboard is a **bank operational overview**. Its job is to answer four questions in less than five seconds:

1. What is the overall health of my bank's request portfolio?
2. Are there operational concerns I should investigate (rejection spikes, stalled requests, missing roles)?
3. What master-data work is pending (staff onboarding, merchant duplicates)?
4. How is my bank trending against recent periods?

It is intentionally not a workflow inbox. There are no approve/review/vote buttons. If a Bank Admin needs to act on a specific request, they navigate into the read-only request detail.

---

**Page header:**

- Greeting: "أهلاً، [first name]"
- Subtitle: "مسؤول [bank name]"
- Read-only oversight chip: "إدارة وعرض" — sets expectations that this dashboard does not surface decisioning controls
- Toolbar (right side): Date-range filter, Refresh, Last-updated timestamp, Export Bank Summary PDF

All dashboard widgets respect the date-range filter.

---

**KPI cards (4-column grid):**

| Card                               | Source                | Color                                | Click-through                            |
| ---------------------------------- | --------------------- | ------------------------------------ | ---------------------------------------- |
| إجمالي طلبات البنك (Total)         | `total`               | Gray                                 | `/requests`                              |
| قيد المعالجة (In Process)          | `in_process`          | Blue                                 | `/requests?tab=in_process`               |
| مُعتمد / مكتمل (Approved / Completed) | `approved_completed`| Green                                | `/requests?tab=completed`                |
| مرفوض (Rejected)                   | `rejected`            | Rose (left-border highlight when % > threshold) | `/requests?tab=rejected`      |

Each card is clickable and routes to the requests list pre-filtered to the matching tab.

---

**Operational health strip (conditional):**

A compact strip appears beneath the KPI cards when one or more of the following operational risk conditions are present:

- Rejection rate above configured threshold this period
- N requests stalled at any single CBY stage > X hours (SLA-aware)
- Missing operational role coverage (no active `BANK_REVIEWER`, no active `SWIFT_OFFICER`)
- Repeated support-returns in the last N days (suggests requester quality issues)
- Suspended staff with active responsibilities

Each entry is a short sentence ("بنكك يفتقد موظف سويفت نشط — قد يتأخر رفع وثائق ما بعد الاعتماد") with a direct click-through.

If no risks are present, the strip is hidden entirely (no decorative "all good" card).

---

**Quick actions (4-column grid):**

- "طلبات البنك" — outline → `/requests`
- "إدارة المستوردين" — outline → `/merchants`
- "إدارة الموظفين" — outline → `/staff`
- "التقارير" — primary blue → `/reports`

---

**Monthly trend chart:**

A full-width SVG line chart card showing per-month bank request volume for the past 6-12 months, with a second line for approved/completed counts. Tooltips on hover show absolute numbers per month. The chart has a clean primary-blue area fill under the volume line.

This chart is decisively analytical — it should support trend-spotting at a glance, not detailed analysis (the Reports page is for that).

---

**Recent bank requests table (compact, max 8 rows):**

Columns: Reference Number, Created By (role chip), Merchant, Amount, Status (full canonical label), Age, View link.

This table is read-only and informational. No bulk actions, no decision buttons. It is sorted by last activity descending.

---

**States:** skeleton on load; "لا توجد بيانات للفترة المختارة" with a date-range hint on empty; inline error with retry.

---

### Requests List (`/requests`)

**Page header:**

- Title: "طلبات تمويل الواردات"
- Subtitle: "محفظة جهتك — عرض إداري"
- Breadcrumbs: الرئيسية → طلبات التمويل
- Read-only oversight chip near the title

No primary "New Request" CTA. (The route exists as an operational fallback; if used at all, it is invoked from a less prominent admin context.)

---

**Stage tabs (operational, not workflow-internal):**

| Tab key       | Label                  | Mapped statuses                                                                          |
| ------------- | ---------------------- | ---------------------------------------------------------------------------------------- |
| `pending`     | معلّق                  | `SUBMITTED`, `BANK_REVIEW`, `BANK_RETURNED`, `SUPPORT_RETURNED`, `DRAFT_REJECTED_INTERNAL` |
| `at_cby`      | لدى البنك المركزي      | `BANK_APPROVED` through `EXECUTIVE_VOTING_CLOSED`                                        |
| `swift_fx`    | السويفت وتأكيد المصارفة | `EXECUTIVE_APPROVED`, `WAITING_FOR_SWIFT`, `SWIFT_UPLOADED`, `FX_CONFIRMATION_PENDING`   |
| `completed`   | مكتمل                  | `CUSTOMS_DECLARATION_ISSUED`, `COMPLETED`                                                |
| `rejected`    | مرفوض                  | `BANK_REJECTED`, `SUPPORT_REJECTED`, `EXECUTIVE_REJECTED`                                |
| `all`         | الكل                   | All of the above                                                                         |

The grouping reflects what an administrator wants to see: where is each request, who currently owns it, where are the bottlenecks.

---

**Toolbar:**

- Search input (reference, merchant, supplier, invoice number, requester name)
- Customize Columns dropdown
- Export button (CSV; filter-scoped; suitable for management exports)
- Saved Views dropdown (e.g. "Aging > 7 days", "High value > $500K")
- Refresh button

No bulk-decision actions. Bulk operations are limited to Export and Print.

---

**Data table:**

| Column           | Description                                                                       |
| ---------------- | --------------------------------------------------------------------------------- |
| (checkbox)       | Bulk selection (export/print only)                                                |
| Reference Number | Monospace                                                                         |
| Created By       | Name + role chip                                                                  |
| Merchant         | Trade name                                                                        |
| Amount           | Formatted amount + currency, right-aligned                                        |
| Status           | Full canonical status badge                                                       |
| Current Owner    | Role chip (DATA_ENTRY / BANK_REVIEWER / CBY-side abstract chip)                   |
| Age in Stage     | Time since last transition                                                        |
| Last Activity    | Relative timestamp                                                                |
| Actions          | View only (no decision actions)                                                   |

Row click navigates to the read-only request detail.

---

### Request Detail (`/requests/[id]`)

The Bank Admin's request detail is a **read-only portfolio inspection view**. It shares its overall layout with the Bank Reviewer's detail page, but the ActionsPanel is empty (no decision buttons).

**Context banners:** same banner set as Bank Reviewer (Correction, SupportRejected as historical context, Locked). No SegregationBlockedBanner — the Bank Admin is not a reviewer.

**Tabs:** المعلومات, الوثائق, الأطراف, السجل (same as Bank Reviewer).

**Documents tab:** Bank Admin can view + download request docs, SWIFT, FX request — but the external FX confirmation PDF row is shown as a locked entry with an explanation tooltip ("تحميل هذا المستند مخصص للجنة التنفيذية ومراجع البنك."). Never silently hidden — the admin should know it exists; they just cannot download it.

**ActionsPanel (right column):**

- No approve/review/return/reject buttons
- A single informational sentence with the current owner and stage: "الطلب حالياً في مرحلة [stage] — المسؤول: [role]"
- For `DRAFT` requests created by the admin themselves (operational fallback case): a "تعديل الطلب" link to the wizard, plus an "حذف المسودة" outline button

---

### Staff (`/staff`)

Bank Admin manages the bank's own staff accounts. This is **identity and access management for own-bank operational users**, not HR management.

---

**Page header:**

- Title: "إدارة الموظفين"
- Subtitle: "موظفو [bank name] المسجلون في المنصة"
- Breadcrumbs: الرئيسية → الموظفون
- Primary action: "+ إضافة موظف" (primary blue)

---

**Access health summary (above the table):**

A compact row of intelligence cards reflecting access posture, not just record counts:

- Active staff
- MFA enabled %
- Suspended / inactive
- Staff with critical roles (BANK_REVIEWER coverage)
- Recent role changes
- Recent permission denials (informational)

Each card is clickable and filters the staff list.

---

**Filter toolbar:**

- Search input (name, email)
- Role filter: All / Data Entry / Bank Reviewer
- Status filter: All / Active / Inactive
- MFA status filter
- Last-login range
- Clear filters link

---

**Data table:**

| Column         | Description                                                            |
| -------------- | ---------------------------------------------------------------------- |
| Employee       | Avatar (initials) + full name + email stacked                          |
| Role           | Role chip (Data Entry / Bank Reviewer)                                 |
| Status         | Active (green) / Inactive (gray) badge                                 |
| MFA            | Enabled / Missing badge                                                |
| Last Login     | Relative + absolute timestamp on hover                                 |
| Workload       | Role-specific count (Data Entry: active drafts; Bank Reviewer: queue size) |
| Created At     | Formatted date (less prominent)                                        |
| Actions        | Three-dot dropdown: View profile, Edit, Deactivate / Reactivate, Reset password, Force logout |

The workload column makes the operational impact of deactivation visible at-a-glance.

---

**Add / Edit Employee modal:**

A shadcn-vue Dialog (24 px radius):

- Full Name (text, required)
- Email (email, required, uniqueness validated server-side)
- Role (select: Data Entry, Bank Reviewer only — CBY roles and `SWIFT_OFFICER` are not selectable here)
- Temporary password (text, required on create; optional on edit; complexity rules visible inline)
- Require MFA toggle (default ON for Bank Reviewer)
- Status: Active / Inactive

Buttons: Cancel | Save. Server-side validation errors render inline. On save, the modal closes and the table refreshes; a success toast surfaces.

If the admin attempts to assign a CBY role or `SWIFT_OFFICER` (e.g., via API manipulation), the backend rejects with a clear error and the UI surfaces a banner explaining the role-assignment scope.

---

**Deactivation flow:**

Deactivating a user with active responsibilities should not be silent.

- Pre-check: if the user has active drafts (Data Entry) or active reviews (Bank Reviewer), the deactivation modal shows an operational-impact warning:
  - "هذا المستخدم لديه [N] طلبات نشطة. إيقاف حسابه سيمنعه من متابعتها."
  - Optional: link to those active items
- Confirmation: AlertDialog requires confirmation; reason field optional but recommended; audit-logged

Reactivation is a simple confirmation toggle.

---

**Sensitive actions:**

- Reset password → triggers a password-reset link sent to the user's email; never displays the new password in the UI
- Force logout → revokes all active sessions; requires confirmation; audit-logged
- Reactivate → simple confirmation toggle

All sensitive actions are audit-logged with actor, target, and timestamp.

---

**States:** skeleton rows; "لم يتم تسجيل موظفين بعد." with the primary "+ إضافة موظف" button on empty; inline error with retry.

---

### Merchants (`/merchants`)

The bank's merchant registry. The Bank Admin maintains the master list of importers/merchants the bank serves.

This page is the bank-scoped counterpart to the CBY Admin's cross-bank merchant intelligence view. Inside the bank, the focus is record completeness, duplicate prevention, and operational readiness for Data Entry.

---

**Page header:**

- Title: "سجل المستوردين"
- Subtitle: "المستوردون المسجلون لدى جهتك"
- Breadcrumbs: الرئيسية → المستوردون
- Primary action: "+ إضافة مستورد"

---

**Quality-summary strip (above the table):**

- Total merchants
- Active merchants
- With incomplete records (missing tax ID, missing registry number)
- Possible duplicates within the bank
- Inactive

Each card filters the table.

---

**Filter toolbar:**

- Search (name, registry number, tax ID)
- Status filter: All / Active / Inactive
- Completeness filter: All / Complete / Missing required fields

---

**Data table:**

| Column                | Description                                            |
| --------------------- | ------------------------------------------------------ |
| Merchant Name         | Trade name                                             |
| Commercial Registry # | Registration identifier                                |
| Tax ID                | Tax card number                                        |
| Status                | Active / Inactive badge                                |
| Linked Requests       | Active + total request count                           |
| Last Activity         | Relative timestamp of latest linked request            |
| Completeness          | Missing-fields warning chip if any required data missing |
| Actions               | View, Edit, Deactivate dropdown                        |

---

**Add / Edit Merchant modal:**

- Trade Name (required)
- Commercial Registry Number (required, uniqueness validated within the bank)
- Tax ID (required, uniqueness validated within the bank)
- Contact Email (optional)
- Contact Phone (optional)
- Notes (optional textarea)

On save, the modal closes and the table refreshes. Duplicate-detection warnings (registry number or tax ID matching an existing merchant) surface inline and require confirmation to proceed.

---

**States:** skeleton on load; reassuring empty state with "+ إضافة مستورد" CTA; inline error with retry.

---

### Reports (`/reports`)

Bank-level operational analytics, scoped to own bank only. The page surfaces volume, value, processing time, decision outcomes, and submission patterns useful for management review.

This is intentionally lighter than the CBY Reports surface (CBY's version is cross-bank with comparison and ranking). Bank-level reports answer "how is my bank doing" rather than "how does my bank compare".

---

**Page header:**

- Title: "التقارير والتحليلات"
- Subtitle: "تحليل أداء طلبات جهتك"
- Breadcrumbs: الرئيسية → التقارير
- Export buttons: CSV (Download icon), PDF (FileText icon)
- Date-range filter (defaults to last 30 days)

---

**5-KPI strip (horizontal row):**

- Total Requests — count + "[X] approved" subtitle
- Financing Value — currency-formatted total in primary currency (with currency-mix tooltip)
- Average Processing Time — days from submit to terminal (shown as "—" if insufficient data)
- Approval Rate — percentage + "[X] rejected" subtitle
- Duplicate Invoices Detected — count + "Alert" label (warning-amber if > 0)

---

**Charts section (2-column grid):**

**Monthly Trend (line chart):**

Two lines — total requests (blue) and approved (green) — across the selected period (last 6-12 months). Tooltips on hover. Legend below the chart.

**Category Distribution (donut chart):**

Distribution by goods type. Legend lists each category with color swatch and percentage. Slice click filters the Reports view to that category for the active period.

**Amount by Currency (horizontal bar chart):**

One bar per currency (USD, EUR, SAR, …) showing total financed value. Bars labeled with the formatted amount.

**Submission Heatmap:**

Grid: rows = days of week (Sat-Thu), columns = hours (08:00-18:00 in 2-hr cells). Cell intensity = submission volume. Used to identify peak intake windows and inform staffing decisions.

---

**Outcomes table:**

A compact table summarizing decision outcomes for the period:

| Stage                          | Approved | Returned | Rejected |
| ------------------------------ | -------- | -------- | -------- |
| Bank Review                    | …        | …        | …        |
| Support Committee              | …        | …        | …        |
| Executive Decision             | …        | n/a      | …        |

Helps identify whether the bank's quality issues sit at intake (high bank-return rate) or at CBY (high support-return / support-rejection rate).

---

**Scheduled reports section:**

A table of recurring report subscriptions: Report Name, Frequency, Recipient Email, Next Run, Status (Active / Paused), Actions (Edit, Pause, Delete).

The Bank Admin can schedule reports addressed to bank-internal recipients only (e.g. branch managers); cross-bank delivery is not supported on this surface.

---

### Notifications (`/notifications`)

Bank Admin receives notifications for:

- Bank-wide request events (informational rollups): "[N] new requests submitted today"
- Staff lifecycle events (account created, deactivated, password reset)
- Merchant registry events (duplicate detected, missing-data alert)
- Bank-level operational concerns (rejection rate spike, role coverage gaps)
- Scheduled report deliveries

Individual workflow notifications (e.g. "Request X was approved") are not delivered to this role — those belong to the requester and reviewer. The admin should consume rollups, not per-request noise.

Page structure (header, tabs, toolbar, table, pagination, empty/loading) is identical to `data-entry.md`.

---

### Settings (`/settings`)

Same 6-tab layout as `data-entry.md`. Notification preferences default to bank-rollup events only; per-request notifications are off by default to prevent noise.

---

### Profile (`/profile`)

Same 3-column layout as `data-entry.md`. Center stats highlight: total staff managed, total merchants managed, last admin action.

---

## Forbidden Actions Reference

BANK_ADMIN cannot, under any UI condition:

- Review, approve, return, or reject any request (bank-side or CBY-side)
- Act as a Bank Reviewer fallback (segregation of duties applies)
- Claim or release support reviews
- Cast, modify, or finalize executive votes
- Upload SWIFT, FX confirmation request, or external FX confirmation documents
- Generate or re-upload the signed external FX confirmation PDF
- Download the signed external FX confirmation PDF (governance-restricted)
- Assign CBY roles (`SUPPORT_COMMITTEE`, `EXECUTIVE_MEMBER`, `COMMITTEE_DIRECTOR`, `CBY_ADMIN`) to bank staff
- Assign `SWIFT_OFFICER` to bank staff (provisioned by CBY Admin)
- Access other banks' staff, merchants, requests, or documents
- Access CBY-side admin surfaces (entities, document rules, system settings, audit, CBY staff)
- Override or bypass workflow constraints

The UI must not render any of the above controls. The role's nav grouping (Main + Administration + Other; no Operations) reflects this scoping.

---

## Cross-Role Handoffs

Bank Admin is administrative — they sit outside the request workflow but are responsible for keeping the workflow operational:

1. **BANK_ADMIN → DATA_ENTRY / BANK_REVIEWER (staff lifecycle):** account creation, role assignment, deactivation. Operational impact of deactivation is surfaced before the action.
2. **BANK_ADMIN → DATA_ENTRY (merchant registry):** maintaining the merchant catalog that Data Entry uses during the wizard's "Importer" select. Duplicates and missing-data issues directly affect intake quality.
3. **BANK_ADMIN → bank stakeholders (reports):** scheduled and ad-hoc exports support managerial review without giving Bank Admin direct workflow authority.
4. **BANK_ADMIN → CBY_ADMIN (escalation, indirect):** when the bank lacks `SWIFT_OFFICER` coverage or has staffing issues, the Bank Admin must escalate to CBY Admin (no direct provisioning of `SWIFT_OFFICER` from this surface).

---

## UX Principles

- This is an administration role, not a workflow role. The UI must not blur that line.
- Read-only request views must visually feel read-only. No disabled-looking decision buttons that would suggest the admin "could" approve.
- Staff/merchant management surfaces emphasize operational impact: deactivating a Bank Reviewer with active queue items should not be silent.
- Reports are managerial overviews, not workflow tools. They support trend-spotting and accountability, not per-request action.
- Bank-scoped boundaries must be enforced visually everywhere — no cross-bank merchants, no cross-bank staff, no cross-bank request hints in search.
- Role-assignment scope must be enforced in the UI (only `DATA_ENTRY` and `BANK_REVIEWER` are selectable) — not just at the backend.
- Notifications should be rollups, not per-request alerts. The admin should not become a relay for transactional workflow events.
- The "operational fallback" of draft creation should not be a primary CTA. It exists as a safety valve, not as routine work.
- Sensitive actions (deactivate, reset password, force logout) require explicit confirmation and audit logging.
- The dashboard's operational health strip must use semantic color and be hidden when nothing is wrong — no decorative "all systems green" cards.

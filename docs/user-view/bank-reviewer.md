# BANK_REVIEWER — Bank Internal Reviewer

Arabic label: مراجع داخلي بالبنك

---

## Role Identity

The internal control gate inside the bank. The Bank Reviewer is the **first decisioning role** in the workflow: every request submitted by a Data Entry user passes through this person before reaching CBY. The reviewer either approves it for the support-committee queue, returns it to the requester for correction, or applies a terminal bank rejection that closes the request permanently.

After CBY processing, the reviewer remains a tracking authority. When a request is rejected by the support committee, it returns to this role for a follow-up decision (keep rejected, or return to Data Entry for correction and resubmission).

BANK_REVIEWER is a **reviewer/decisioning role with downstream observation duties**. It is not an intake role and not a CBY governance role. It exists specifically to enforce segregation of duties inside the bank: the person who submits a request cannot be the person who approves it.

---

## Operational Posture

| Aspect              | Tone                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------- |
| Work mode           | Reviewer / decisioning (read-then-act)                                                |
| Primary surface     | Bank review queue (`SUBMITTED` / `BANK_REVIEW`)                                       |
| Secondary surface   | Downstream tracking — CBY support, voting, SWIFT, FX completion outcomes              |
| Tertiary surface    | Post-support-rejection decision queue (`SUPPORT_REJECTED`)                            |
| Status language     | Full canonical CBY workflow labels — the reviewer needs the real picture              |
| Visual density      | Medium-high: document-heavy review surfaces, audit-aware actions                      |
| Decision tone       | Calm, deliberate, audit-aware. All decisions require explicit reasons                 |

---

## Scope & Boundary

- **Organization scope:** Own bank only. The reviewer never sees requests, merchants, or staff from another bank.
- **Status scope:** All canonical statuses are visible with full internal labels. Unlike DATA_ENTRY, this role needs to see "where exactly a request is at CBY" — `SUPPORT_REVIEW_IN_PROGRESS`, `EXECUTIVE_VOTING_OPEN`, `WAITING_FOR_SWIFT`, `FX_CONFIRMATION_PENDING` are all surfaced verbatim.
- **Action scope:** Decisioning is limited to two stages — bank review (`SUBMITTED` / `BANK_REVIEW`) and post-support-rejection (`SUPPORT_REJECTED`). All other stages are read-only for this role.
- **Segregation of duties (enforced):** the reviewer cannot decide on a request they created themselves. Backend rejects the attempt; the UI must not render the action buttons in that case (do not rely on an error dialog).

---

## Workflow Authority Summary

| Stage                              | BANK_REVIEWER authority                                                  |
| ---------------------------------- | ------------------------------------------------------------------------ |
| `DRAFT`                            | Read-only (cannot create requests as the primary work mode)              |
| `SUBMITTED`                        | Start review → transitions to `BANK_REVIEW`                              |
| `BANK_REVIEW`                      | Approve (→ `BANK_APPROVED`), Return (→ `BANK_RETURNED`), Reject (→ `BANK_REJECTED`) |
| `BANK_RETURNED`                    | Read-only; waiting on Data Entry                                         |
| `BANK_APPROVED` through `SWIFT_UPLOADED` | Read-only monitoring                                               |
| `SUPPORT_REJECTED`                 | Keep rejected (no transition, acknowledged) or Return to Data Entry (→ `BANK_RETURNED`) |
| `SUPPORT_RETURNED`                 | Read-only; waiting on Data Entry                                         |
| `EXECUTIVE_*`, `FX_CONFIRMATION_PENDING`, `COMPLETED` | Read-only monitoring                                  |
| All terminal/rejected statuses     | Read-only; no resubmission                                               |
| `BANK_REJECTED`                    | Terminal; reviewer cannot reverse it                                     |

The terminal bank rejection (`BANK_REJECTED`) is **irreversible**. Once applied, the request cannot be reopened, edited, or resubmitted by any role. The UI must surface this clearly in the confirmation dialog.

---

## Document Authority

| Document                            | Access                |
| ----------------------------------- | --------------------- |
| Request documents (intake)          | View + Download / Own bank |
| SWIFT document                      | View + Download / Own bank |
| FX confirmation request document    | View + Download / Own bank |
| External FX confirmation PDF        | View + Download / Own bank |

BANK_REVIEWER has the broadest bank-side download authority. This is intentional: the reviewer is the bank-side record-keeper for the request, and the bank may need to retrieve the final signed external FX confirmation for its own files.

---

## Sidebar Navigation

| Group            | Item                          | Route          |
| ---------------- | ----------------------------- | -------------- |
| الرئيسية (Main)  | اللوحة الرئيسية (Dashboard)   | /dashboard     |
| الرئيسية         | طلبات التمويل (Requests)      | /requests      |
| الرئيسية         | الإشعارات (Notifications)     | /notifications |
| الأخرى (Other)   | الإعدادات (Settings)          | /settings      |

The Operations group is intentionally absent — the reviewer does not create requests. The Administration group is absent — the reviewer does not manage staff or merchants. The sidebar footer provides access to Profile.

---

## Pages

### Login (`/login`)

Identical structure to the login page described in `data-entry.md`. Same authentication form, MFA flow, rate limit (5/min/IP), and lockout (10 failures / 15-min lock). Same inactivity-redirect banner.

---

### Dashboard (`/dashboard`)

The Bank Reviewer dashboard is a **decisioning launcher with downstream visibility**. Its job is to answer four questions in less than five seconds:

1. How many requests are waiting for my review right now?
2. Are there support rejections I need to act on?
3. What is the downstream status of requests I have already approved?
4. Are any of my approved requests stuck or aged at CBY?

It deliberately does not include claim controls, voting controls, SWIFT upload controls, or any other-bank data. It does include enough downstream visibility for the reviewer to track the outcomes of decisions they have made.

---

**Page header:**

- Greeting: "أهلاً، [first name]"
- Subtitle: "مراجع داخلي بـ[bank name]"
- No primary action button (reviewers do not create requests)

---

**Action-required strip (conditional, highest priority):**

When the user has any requests in `SUPPORT_REJECTED` awaiting a bank-side decision, an amber alert strip appears at the very top.

- Icon: AlertTriangle (amber)
- Title: "[N] طلبات رفضتها لجنة المساندة وتنتظر قرارك"
- Subtitle: lists the first reference and rejection reason snippet
- Action: "اتخاذ القرار" → opens `/requests?tab=support_rejected`

This strip is the highest-attention element on the page because these requests are stalled awaiting a bank-side decision.

---

**KPI cards (4-column grid → 2 on tablet → 1 on mobile):**

| Card                                  | Source              | Color                                    | Click-through                          |
| ------------------------------------- | ------------------- | ---------------------------------------- | -------------------------------------- |
| بانتظار مراجعتي (Pending Review)      | `pending_review`    | Amber (left-border highlight when > 0)   | `/requests?tab=pending`                |
| رُفض من المساندة (Rejected by Support) | `rejected_by_support` | Rose (left-border highlight when > 0)  | `/requests?tab=support_rejected`       |
| قيد البنك المركزي (At CBY)            | `at_cby`            | Blue                                     | `/requests?tab=at_cby`                 |
| مُعتمد / مكتمل (Approved / Completed) | `approved_completed`| Green                                    | `/requests?tab=completed`              |

All cards are clickable, cursor-pointer, with hover state. Counts update in real time on dashboard refresh.

---

**Quick actions (2-column grid):**

- "طابور المراجعة" (Review Queue) — primary blue card with FileCheck icon → `/requests?tab=pending`
- "كل طلبات البنك" (All Bank Requests) — outline card with List icon → `/requests`

---

**Review queue table (compact, max 8 rows):**

Shows requests in `SUBMITTED` or `BANK_REVIEW`, sorted by age (oldest first).

| Column           | Description                                                                  |
| ---------------- | ---------------------------------------------------------------------------- |
| Reference Number | Monospace                                                                    |
| Submitted By     | Name + role chip (`DATA_ENTRY`); used to enforce segregation visually        |
| Merchant         | Trade name                                                                   |
| Amount           | Formatted amount + currency, right-aligned                                   |
| Status           | Internal status badge (`SUBMITTED` blue / `BANK_REVIEW` amber)               |
| Age              | Time since submission; turns amber after configured warning threshold       |
| Actions          | "بدء المراجعة" if `SUBMITTED`; "متابعة" if `BANK_REVIEW`                     |

If the requester is the current user, the action button is **disabled** with a tooltip "لا يمكنك مراجعة طلب أنشأته بنفسك" — the row should not silently 403 on click.

---

**Downstream tracking table (compact, max 5 rows):**

A second, secondary-styled table showing recently-approved requests as they move through CBY. Columns: Reference Number, Stage (current CBY stage label), Last Activity, Age in current stage, View link.

This satisfies the reviewer's duty to track outcomes after bank approval. It is intentionally compact — the main investigation should happen on the requests list.

---

**Loading / empty / error states:**

- **Loading:** skeleton rows in both tables; skeleton KPI cards.
- **Empty review queue:** illustration + "لا توجد طلبات في طابور المراجعة حالياً ✓" (reassuring tone; this is a healthy state).
- **Empty downstream:** hide the section entirely if no approved requests have entered CBY in the last N days.
- **Error:** inline error card with retry.

---

### Requests List (`/requests`)

**Page header:**

- Title: "طلبات تمويل الواردات"
- Subtitle: "طلبات جهتك فقط — مراجعة داخلية ومتابعة المسار"
- Breadcrumbs: الرئيسية → طلبات التمويل

---

**Stage tabs (order reflects operational priority):**

| Tab key             | Label                       | Mapped statuses                                                 |
| ------------------- | --------------------------- | --------------------------------------------------------------- |
| `pending`           | قيد المراجعة                | `SUBMITTED`, `BANK_REVIEW`                                      |
| `support_rejected`  | رفض من المساندة             | `SUPPORT_REJECTED`                                              |
| `bank_returned`     | أُعيد للمدخل من البنك       | `BANK_RETURNED`                                                 |
| `support_returned`  | أُعيد للمدخل من المساندة    | `SUPPORT_RETURNED`                                              |
| `at_cby`            | لدى البنك المركزي           | `BANK_APPROVED` through `FX_CONFIRMATION_PENDING`               |
| `completed`         | مكتمل                       | `CUSTOMS_DECLARATION_ISSUED`, `COMPLETED`                       |
| `rejected`          | مرفوض نهائياً               | `BANK_REJECTED`, `EXECUTIVE_REJECTED`                           |
| `all`               | الكل                        | All of the above                                                |

The `pending` and `support_rejected` tabs sit first because they represent work this role must act on.

---

**Toolbar:**

- Search input (reference, merchant, supplier, invoice number)
- Customize Columns dropdown
- Export button (CSV, filter-scoped)
- "Created by me" toggle — quickly hides requests the user originally submitted (segregation-of-duties helper)

Bulk-select toolbar (replaces the search when ≥1 row selected): selection count chip, Export selected, Print selected, Clear selection. No bulk approve/reject/return — those decisions are individual and reasoned.

---

**Data table (full internal labels — no simplification):**

| Column           | Description                                                                  |
| ---------------- | ---------------------------------------------------------------------------- |
| (checkbox)       | Bulk selection                                                               |
| Reference Number | Monospace                                                                    |
| Created By       | Name + role chip; used to make segregation visible at-a-glance               |
| Merchant         | Trade name (hidable)                                                         |
| Goods            | Goods category (hidable)                                                     |
| Amount           | Formatted amount + currency (hidable)                                        |
| Status           | Full canonical status badge with internal label                              |
| Progress         | Horizontal progress bar                                                      |
| Age in Stage     | Time since last status change                                                |
| Last Activity    | Relative timestamp + last actor                                              |
| Actions          | Row chevron; "بدء المراجعة" / "متابعة" appears inline when applicable        |

Row click navigates to `/requests/[id]`. The "Created by me" filter chips remain visible across pagination.

**States:** skeleton rows on load; "لا توجد طلبات مطابقة" with clear-filter link on filtered empty; inline error with retry.

---

### Request Detail (`/requests/[id]`)

The reviewer's primary working surface. The page must support fast decisioning: documents reachable in one click, return/reject reason capture is friction-free but explicit, and segregation-of-duties is enforced at the action layer (not just at submit).

---

**Page header:**

- Title: Request reference (monospace)
- Breadcrumbs: الرئيسية → طلبات التمويل → [Reference]
- Current status badge (full canonical label)
- Print button → `/requests/[id]/print`
- Audit-trail snapshot link (opens the activity log tab pre-scrolled to the most recent transition)

---

**Context banners (top, mutually exclusive in priority order):**

1. **SegregationBlockedBanner** (gray with info-blue accent) — appears when the current user created this request. Shows: "لا يمكنك مراجعة طلب أنشأته بنفسك. تم إخفاء أزرار القرار." This explains why the ActionsPanel does not have decision buttons, instead of leaving the user confused.
2. **SupportRejectedBanner** (rose) — appears when status = `SUPPORT_REJECTED`. Shows the rejecting reviewer's name + role chip + rejection reason + timestamp, plus two primary CTAs: "إبقاء الرفض" and "إعادة للمدخل للتعديل".
3. **CorrectionBanner** (amber) — appears when the request was previously returned and is back in the review queue. Shows the original return reason as historical context so the reviewer can verify the correction.
4. **LockedBanner** (gray; red variant for terminal) — appears for `BANK_REJECTED`, `SUPPORT_REJECTED` (after acknowledgment), `EXECUTIVE_REJECTED`, `COMPLETED`, `CUSTOMS_DECLARATION_ISSUED`.

The reviewer does not see ActiveReviewBanner, ClaimedByOthersBanner, or VotingPanel — those belong to other roles.

---

**WorkflowProgress:**

Horizontal stage progress bar using the full canonical lifecycle. Current stage in primary blue, completed in green, future in muted gray. Return loops render visibly when applicable (`BANK_RETURNED` arrow back to `DRAFT`).

---

**Tabs:**

| Tab            | Purpose                                                                              |
| -------------- | ------------------------------------------------------------------------------------ |
| المعلومات      | Request and supplier/shipment fields in grouped sections                             |
| الوثائق        | DocumentChecklist scoped to documents this role may download (request, SWIFT, FX request, external FX confirmation) |
| الأطراف        | Actors at each stage with role chips, timestamps, and reasons where applicable       |
| السجل          | Full canonical stage history with actor + transition + reason (audit-style, not raw enum dump but business-readable) |

---

**Overview tab (المعلومات):**

Grouped read-only sections (same field layout as the wizard's read-only view). The reviewer reviews these fields against the uploaded documents on the Documents tab.

---

**Documents tab (الوثائق):**

DocumentChecklist showing every document attached to the request:

| Document                            | Reviewer access (own bank)                  |
| ----------------------------------- | ------------------------------------------- |
| Proforma Invoice                    | View + Download                             |
| Commercial Registry                 | View + Download                             |
| Tax Card                            | View + Download                             |
| Sector-specific License (if any)    | View + Download                             |
| Additional supporting documents     | View + Download                             |
| SWIFT document (after SWIFT stage)  | View + Download                             |
| FX confirmation request document    | View + Download                             |
| External FX confirmation PDF (signed) | View + Download — after Director completes |

Each row shows: document name, file name, upload timestamp, uploader name + role, file size, Download button, and an inline preview link where supported. Documents from other banks are not listed at all.

---

**Parties tab (الأطراف):**

Full actor list across all stages so far: Submitter, Reviewer (own role's actions on this request), Support claimant (if any), Executive members who voted, Committee Director (vote + finalization + FX completion). Each entry shows name, role chip, organization (CBY / bank name), action performed, timestamp, and reason if applicable.

---

**Activity log tab (السجل):**

Stage-by-stage business-readable history. Each entry includes: stage, transition timestamp, actor name + role + organization, and reason text where applicable. System-generated automatic transitions (e.g. "تم فتح التصويت التنفيذي تلقائياً بعد اعتماد المساندة") are explicitly labeled as system events.

This is more detailed than DATA_ENTRY's activity log but still business-readable. The raw audit page is a separate CBY-only surface.

---

**ActionsPanel (right column):**

Composition depends on current status and whether the user is the original requester.

**If current user created the request:** no action buttons. The SegregationBlockedBanner at the top explains why.

**Status = `SUBMITTED`:**

- "بدء المراجعة" (primary) — transitions to `BANK_REVIEW`. Optimistic UI update; rollback on 4xx.

**Status = `BANK_REVIEW`:**

- "اعتماد الطلب" (primary green) — transitions to `BANK_APPROVED`. Opens a confirmation modal:
  - Modal title: "اعتماد الطلب وإرساله إلى لجنة المساندة"
  - Body: short summary (reference, merchant, amount) + optional reviewer note
  - Buttons: "إلغاء" / "اعتماد" (primary green)
- "إعادة إلى المدخل للتعديل" (outline amber) — opens a return dialog requiring a mandatory reason text (min 10 chars). On confirm, transitions to `BANK_RETURNED`. Modal includes an optional checkbox list of fields/documents flagged for the requester to correct.
- "رفض نهائي" (destructive red) — opens a hard-confirmation dialog:
  - Title: "رفض نهائي للطلب"
  - Warning: "هذا الإجراء نهائي ولا يمكن التراجع عنه. لن يستطيع موظف الإدخال إعادة فتح الطلب."
  - Mandatory reason textarea (min 20 chars)
  - "إلغاء" / "تأكيد الرفض النهائي" (destructive); the confirm button stays disabled until the reason meets the minimum length

**Status = `SUPPORT_REJECTED`:**

- "إبقاء الرفض" (outline) — acknowledges the rejection; no status transition; surfaces a confirmation toast and renders the LockedBanner afterwards
- "إعادة إلى المدخل للتعديل" (primary amber) — transitions to `BANK_RETURNED`. Same return dialog as above; the reviewer can quote the support rejection reason as context for the requester

**Other statuses:** the ActionsPanel shows only the LockedBanner explanation (read-only). No retry/withdraw/escalate buttons exist — those are not part of this role's authority.

---

**Confirmation dialogs:**

All return, reject, and terminal-reject dialogs use the shadcn-vue AlertDialog component:

- 24 px border radius
- Title in bold, description in body text
- Mandatory reason textarea with character counter
- Cancel (outline) + Confirm (semantic color matching the action)
- Confirm button disabled until reason meets the minimum length requirement
- Escape key closes the dialog (treated as cancel)
- Reason text is stored on the workflow transition AND mirrored to the audit log entry

---

### Print Request (`/requests/[id]/print`)

Same print-optimized A4 layout as DATA_ENTRY's print view, with the reviewer's name appended in the footer ("Reviewed by: [name]") when applicable.

---

### Notifications (`/notifications`)

The Bank Reviewer receives notifications for:

- New request submitted to bank review (own bank)
- Request returned (DATA_ENTRY corrected and resubmitted; back in queue)
- Support committee approved / rejected / returned the request
- Executive voting opened / closed / finalized (informational)
- SWIFT uploaded (own bank); FX confirmation completed (own bank)
- Audit alert: forbidden-action attempt by a bank user (informational only)

Voting tally details, claim ownership transfers, and other-bank events are not delivered to this role.

Page structure (header, tabs, toolbar, table, pagination, empty/loading states) is identical to the notifications page described in `data-entry.md`.

---

### Settings (`/settings`)

Same 6-tab layout as described in `data-entry.md` (General, Security, Notifications, MFA, Demo, Appearance). Notification preferences default to all enabled for reviewer-relevant events; the user can disable individual categories.

---

### Profile (`/profile`)

Same 3-column layout as described in `data-entry.md`. Stats card on the left shows: total reviews performed, approvals, returns, terminal rejections — useful for self-tracking and for management's reviewer-quality reporting (the underlying numbers are also surfaced in CBY reports).

---

## Forbidden Actions Reference

BANK_REVIEWER cannot, under any UI condition:

- Create requests as a primary work mode (operational creation may exist as a fallback per implementation, but the role's nav explicitly does not surface New Request)
- Review or decide on a request they themselves submitted
- Claim or release a support review (CBY operational role)
- Cast, modify, or finalize executive votes
- Upload SWIFT, FX confirmation request, or external FX confirmation documents
- Generate or re-upload the signed external FX confirmation PDF
- Reverse a terminal `BANK_REJECTED` decision
- Access other banks' requests, merchants, staff, or documents
- Manage bank staff or merchants (those belong to `BANK_ADMIN`)
- Access CBY-side admin surfaces (entities, document rules, system settings)

The UI must not render any of these controls. If backend exposes them by mistake, frontend drops them defensively.

---

## Cross-Role Handoffs

BANK_REVIEWER sits at the **bank-to-CBY boundary**. Every decision here has downstream consequences:

1. **DATA_ENTRY → BANK_REVIEWER (on submit):** request enters the review queue. The reviewer should not be the requester; the UI hides decision buttons if they are.
2. **BANK_REVIEWER → DATA_ENTRY (on return):** the CorrectionBanner with the reviewer's reason appears in Data Entry's wizard. Required reason text becomes the primary correction guidance for the requester.
3. **BANK_REVIEWER → SUPPORT_COMMITTEE (on approve):** the request transitions to `BANK_APPROVED` and is auto-queued into the CBY support pool. The reviewer can monitor downstream but cannot retract.
4. **BANK_REVIEWER → terminal (`BANK_REJECTED`):** irreversible. The hard-confirmation dialog must make this explicit. Audit log captures the reason and actor permanently.
5. **SUPPORT_COMMITTEE → BANK_REVIEWER (on support rejection):** request returns for a bank-side decision. The SupportRejectedBanner surfaces the rejection reason and offers the two-choice action (keep / return for correction).
6. **SUPPORT_COMMITTEE → DATA_ENTRY (on support return):** the reviewer does not need to act; the banner on the request explains the return; the requester handles correction.

---

## UX Principles

- The dashboard is a decisioning launcher. The review queue is its center of gravity.
- Segregation of duties is enforced at the UI layer, not just the API. Decision buttons are not rendered for the user who created the request.
- Every consequential decision (approve, return, reject, terminal-reject) requires an explicit reason. Reason text is mandatory, audit-logged, and presented back to the requester verbatim.
- Terminal rejections must feel different from returns. The confirmation dialog uses destructive coloring and an explicit irreversibility warning.
- Status labels are full canonical CBY enums (not simplified). The reviewer needs the real picture to monitor downstream.
- Documents from CBY-controlled stages (SWIFT, FX confirmation) are visible and downloadable as soon as they exist — this role is the bank's record-keeper.
- Downstream visibility is for monitoring, not action. No retry, withdraw, or escalate buttons exist beyond the bank-side decision moments.
- The activity log is business-readable, not a raw audit dump. Stage transitions show actor + reason; system-generated transitions are labeled.
- Confirmation dialogs use minimum-length reason validation. The submit button stays disabled until the reason is substantive.
- The role does not exist to maximize approval rate. It exists to enforce control. The UI should feel deliberate, audit-aware, and resistant to rushed clicks.

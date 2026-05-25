# SUPPORT_COMMITTEE — Support Committee Member

Arabic label: عضو لجنة المساندة

---

## Role Identity

The first CBY-side reviewer in the workflow. After a bank approves a request (`BANK_APPROVED`), it auto-enters the **shared global support queue** as `SUPPORT_REVIEW_PENDING`. Any active member of the Support Committee can claim a request from this queue, review it, and decide its fate — approve (sending it on to executive voting), return to the bank's Data Entry for correction, or reject with reason.

This role is operated through a **claim/release ownership model**. A member must explicitly claim a request before any decision controls become available. Once claimed, the request is locked to that reviewer for up to **15 minutes of inactivity**, with the frontend sending a heartbeat every **60 seconds** to keep the claim alive while the page is open. If the heartbeat lapses (browser closed, network drop, idle), the claim auto-releases and the request returns to the unclaimed pool.

SUPPORT_COMMITTEE is a **CBY-global operational role with claim-based ownership**. It is not bank-scoped, not a governance role, and has no authority over executive voting, SWIFT, or external FX confirmation.

---

## Operational Posture

| Aspect              | Tone                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------- |
| Work mode           | Operational reviewer with claim ownership                                             |
| Primary surface     | Shared global support queue with claim/release controls                               |
| Secondary surface   | Active claim work surface (claimed request detail page)                               |
| Status language     | Full canonical CBY workflow labels                                                    |
| Visual density      | Medium-high: document-heavy review surface, claim-state visibility, audit-aware       |
| Decision tone       | Calm, deliberate, audit-aware. All decisions require explicit reasons                 |
| Ownership feel      | Strong visual distinction between "mine", "others'", and "unclaimed"                  |

---

## Scope & Boundary

- **Organization scope:** All banks (CBY-global). The queue is shared across all Support Committee members.
- **Claim scope:** A member can only decide on requests they have personally claimed. Decisions on requests claimed by others are blocked at both the UI and the backend.
- **Status scope:** Decisioning is limited to `SUPPORT_REVIEW_IN_PROGRESS`. Earlier stages (`SUBMITTED`, `BANK_REVIEW`, `BANK_APPROVED`) and later stages (`EXECUTIVE_*`, `SWIFT_*`, `FX_CONFIRMATION_PENDING`, `COMPLETED`) are read-only context for this role.
- **Concurrency:** Claims are atomic at the backend (`SELECT FOR UPDATE` + Redis TTL key). If two members attempt to claim the same request simultaneously, only one succeeds; the loser sees a 409 error with a clear explanation.

---

## Workflow Authority Summary

| Stage                              | SUPPORT_COMMITTEE authority                                              |
| ---------------------------------- | ------------------------------------------------------------------------ |
| `SUBMITTED` through `BANK_APPROVED` | Read-only — request not yet in support scope                            |
| `SUPPORT_REVIEW_PENDING`           | Claim the request (atomic; transitions to `SUPPORT_REVIEW_IN_PROGRESS`)  |
| `SUPPORT_REVIEW_IN_PROGRESS` (claimed by me) | Approve (→ `SUPPORT_APPROVED`), Return (→ `SUPPORT_RETURNED`), Reject (→ `SUPPORT_REJECTED`), Release claim (→ `SUPPORT_REVIEW_PENDING`) |
| `SUPPORT_REVIEW_IN_PROGRESS` (claimed by others) | Read-only; decision buttons hidden                            |
| `SUPPORT_APPROVED`, `SUPPORT_RETURNED`, `SUPPORT_REJECTED` | Read-only after decision                          |
| All `EXECUTIVE_*`, `SWIFT_*`, FX, terminal states | Read-only monitoring                                       |

---

## Claim Lifecycle

The claim model is the central UX concern for this role. The lifecycle has four states:

| Claim state                       | Meaning                                                              | Owner can decide?            |
| --------------------------------- | -------------------------------------------------------------------- | ---------------------------- |
| Unclaimed                         | Request is in `SUPPORT_REVIEW_PENDING`; nobody has claimed it        | No, but anyone can claim     |
| Claimed by me                     | Current user is the claim owner; heartbeat is active                 | Yes, full decision authority |
| Claimed by others                 | Another committee member holds the claim                             | No, blocked at UI and API    |
| Expired (silent release)          | Heartbeat lapsed; backend released the claim                         | Request returns to unclaimed |

**Claim mechanics:**

- Atomic claim: `POST /api/workflow/{id}/claim-support-review` uses `SELECT ... FOR UPDATE` to prevent dual claims. The backend writes a Redis key `support_claim:{request_id}` with 15-minute TTL.
- Heartbeat: while the user has the request detail page open, the frontend `POST`s `/api/workflow/{id}/claim-support-review/heartbeat` every 60 seconds. Each heartbeat resets the Redis TTL to 15 minutes.
- Voluntary release: `DELETE /api/workflow/{id}/claim-support-review` releases ownership; the request returns to `SUPPORT_REVIEW_PENDING`.
- Inactive expiry: if no heartbeat arrives for 15 minutes, the Redis key expires; a scheduled job (`ExpireClaimsCommand`) cleans up the workflow state and returns the request to the unclaimed pool.
- Notification on auto-release: the claim owner receives a `CLAIM_RELEASED` notification (per user preference) when their claim is reaped by the TTL expiry.

The UI must visually reinforce the claim state at all times: in the queue table, on the request detail page, in the active-review banner, and in the notification stream.

---

## Document Authority

| Document                            | Access                |
| ----------------------------------- | --------------------- |
| Request documents (intake)          | View + Download / All banks |
| SWIFT document                      | No                    |
| FX confirmation request document    | No                    |
| External FX confirmation PDF        | No                    |

SUPPORT_COMMITTEE reviews intake-stage documents (Proforma Invoice, Commercial Registry, Tax Card, sector-specific licenses, supporting documents). Post-approval documents (SWIFT, FX request, signed external FX confirmation) are out of scope for support review and intentionally inaccessible — even for cross-context investigation.

---

## Sidebar Navigation

| Group            | Item                          | Route          |
| ---------------- | ----------------------------- | -------------- |
| الرئيسية (Main)  | اللوحة الرئيسية (Dashboard)   | /dashboard     |
| الرئيسية         | طلبات التمويل (Requests)      | /requests      |
| الرئيسية         | الإشعارات (Notifications)     | /notifications |
| الأخرى (Other)   | الإعدادات (Settings)          | /settings      |

No Operations group, no Administration group. The sidebar footer provides access to Profile.

---

## Pages

### Login (`/login`)

Identical to the login page in `data-entry.md`.

---

### Dashboard (`/dashboard`)

The Support Committee dashboard is a **claim-aware queue console**. Its job is to answer three questions in less than five seconds:

1. Are any requests in the queue waiting to be claimed (highest-priority work)?
2. Do I have an active claim that I need to return to?
3. What is the queue's overall pressure right now?

It deliberately does not include voting tallies, SWIFT controls, or FX confirmation status — those belong to other roles' surfaces.

---

**Page header:**

- Greeting: "أهلاً، [first name]"
- Subtitle: "عضو لجنة المساندة"
- Read-only chip: "نطاق عبر البنوك" — sets expectation that visibility is cross-bank (this is by design)

---

**Active-claim strip (conditional, highest priority):**

If the current user has at least one active claim, a strip appears beneath the header:

- Icon: Indigo Clock (claim icon)
- Title: "لديك [N] طلب نشط محجوز باسمك"
- Subtitle: shows the oldest claim's reference + time since claimed
- Action: "متابعة المراجعة" → opens the oldest claimed request's detail page

Active claims must be the most visually prominent thing on the dashboard, even more so than the unclaimed queue. A forgotten claim blocks the queue for up to 15 minutes per request.

---

**KPI cards (4-column grid):**

| Card                                       | Source                    | Color                                | Click-through                              |
| ------------------------------------------ | ------------------------- | ------------------------------------ | ------------------------------------------ |
| بانتظار المطالبة (Waiting for Claim)       | `waiting_for_claim`       | Amber (left-border highlight when > 0) | `/requests?tab=waiting`                  |
| أعمل عليها الآن (Active by Me)             | `active_by_me`            | Indigo (left-border highlight when > 0) | `/requests?tab=my_claims`               |
| محجوزة لأعضاء آخرين (Claimed by Others)    | `claimed_by_others`       | Gray                                 | `/requests?tab=in_progress`                |
| اعتُمِدت مؤخراً (Recently Approved)        | `recently_approved`       | Green                                | `/requests?tab=approved`                   |

Counts update in real time as claims change ownership.

---

**Quick actions (2-column grid):**

- "طابور المراجعة" (Support Queue) — primary indigo card → `/requests`
- "الإشعارات" — outline card → `/notifications` (with unread count badge)

---

**Support queue table (compact, max 8 rows):**

Shows requests in `SUPPORT_REVIEW_PENDING` (top) and `SUPPORT_REVIEW_IN_PROGRESS` (below). Sorted by age first, then by claim state.

| Column           | Description                                                                              |
| ---------------- | ---------------------------------------------------------------------------------------- |
| Reference Number | Monospace                                                                                |
| Bank             | Originating bank name                                                                    |
| Supplier         | Supplier name (intake data)                                                              |
| Amount           | Formatted amount + currency, right-aligned                                               |
| Status           | Status badge                                                                             |
| Claim Owner      | "غير مطالب به" (unclaimed); "[Name] (أنت)" (claimed by me); reviewer name (claimed by others) |
| Age in Stage     | Time since transition into the support queue                                             |
| Actions          | "مطالبة" (primary) if unclaimed; "متابعة" (outline) if mine; "عرض" (ghost) if others'    |

Row tinting reinforces claim state visually:

- Unclaimed: standard white background
- Claimed by me: faint indigo-tinted row background + "(أنت)" suffix in the Claim Owner cell
- Claimed by others: muted background, claim owner name shown, action button non-decisive (view only)

---

**States:**

- **Loading:** skeleton rows in queue table + skeleton KPI cards
- **Empty (no pending or active work):** reassuring illustration with "لا توجد طلبات بانتظار المراجعة حالياً ✓" — healthy state, not a problem
- **Error:** inline error card with retry

---

### Requests List (`/requests`)

Scope: all banks. The list is the CBY-global support-stage registry.

**Page header:**

- Title: "طلبات تمويل الواردات"
- Subtitle: "جميع الطلبات في مرحلة المساندة عبر البنوك"
- Breadcrumbs: الرئيسية → طلبات التمويل

---

**Stage tabs (claim-aware):**

| Tab key       | Label                       | Mapped statuses                                                       |
| ------------- | --------------------------- | --------------------------------------------------------------------- |
| `waiting`     | بانتظار المطالبة            | `BANK_APPROVED`, `SUPPORT_REVIEW_PENDING`                             |
| `my_claims`   | محجوز باسمي                 | `SUPPORT_REVIEW_IN_PROGRESS` claimed by current user                  |
| `in_progress` | محجوز لأعضاء آخرين          | `SUPPORT_REVIEW_IN_PROGRESS` claimed by other users                   |
| `approved`    | معتمد                       | `SUPPORT_APPROVED`                                                    |
| `returned`    | أُعيد للمدخل                | `SUPPORT_RETURNED`                                                    |
| `rejected`    | مرفوض                       | `SUPPORT_REJECTED`                                                    |
| `all`         | الكل                        | All support-stage statuses                                            |

The `my_claims` and `waiting` tabs are operationally first.

---

**Toolbar:**

- Search input (reference, supplier, merchant, invoice number, bank name)
- Bank filter dropdown: "جميع البنوك" default; one entry per bank
- Customize Columns dropdown
- Export button (CSV; filter-scoped)
- Refresh button
- "إخفاء المحجوز للآخرين" toggle (operational helper — quickly hides claims held by other members when scanning for available work)

No bulk-decision actions. Claims are individual and ownership-bound.

---

**Data table:**

| Column           | Description                                                                              |
| ---------------- | ---------------------------------------------------------------------------------------- |
| Reference Number | Monospace                                                                                |
| Bank             | Originating bank (hidable)                                                               |
| Merchant         | Trade name (hidable)                                                                     |
| Supplier         | Supplier name (hidable)                                                                  |
| Amount           | Formatted amount + currency (hidable)                                                    |
| Status           | Full canonical status badge                                                              |
| Claim Owner      | Same conventions as the dashboard queue table                                            |
| Age in Stage     | Time since transition; turns amber after configured warning threshold                    |
| Actions          | Claim-state-dependent (Claim / Resume / View)                                            |

Row click → request detail. The "إخفاء المحجوز للآخرين" toggle persists across pagination.

---

### Request Detail (`/requests/[id]`)

The Support Committee member's primary working surface. The page must make claim state unambiguous and provide friction-free access to documents, decision controls, and reason capture.

**Page header:**

- Title: Request reference (monospace)
- Breadcrumbs: الرئيسية → طلبات التمويل → [Reference]
- Status badge + Claim state chip (e.g. "محجوز باسمي" indigo / "محجوز لـ[name]" gray / "غير مطالب به" amber)
- Print button → `/requests/[id]/print`

---

**Context banners (top, mutually exclusive in priority order):**

1. **ActiveReviewBanner** (indigo) — appears when the current user has claimed this request and is actively reviewing.
   - Title: "تراجع هذا الطلب الآن"
   - Subtitle: claim acquired timestamp + time remaining before TTL expiry (e.g. "متبقي 12:30 من 15:00")
   - Heartbeat indicator (small green pulsing dot) confirming the heartbeat is reaching the server
   - Action buttons: "إطلاق المطالبة" (outline) — releases the claim immediately
   - While this banner is visible, the frontend pings the heartbeat endpoint every 60 seconds. If a heartbeat fails (network blip), the dot turns amber and a retry occurs; if heartbeats fail repeatedly, the banner converts to an error state with a "تحديث الصفحة" action.

2. **ClaimedByOthersBanner** (gray) — appears when the request is in `SUPPORT_REVIEW_IN_PROGRESS` and claimed by another member.
   - Title: "محجوز حالياً لـ[reviewer name]"
   - Subtitle: claim acquired timestamp
   - Action buttons: none (the page is read-only); a "إشعار عند الإفراج" optional toggle if backend supports it
   - All decision buttons are hidden (not disabled — hidden) to reinforce that this is not your work

3. **UnclaimedBanner** (amber) — appears when the request is in `SUPPORT_REVIEW_PENDING` and not claimed.
   - Title: "هذا الطلب جاهز للمطالبة"
   - Subtitle: time since entering the queue
   - Action: "مطالبة بالطلب" (primary indigo) — atomically claims the request; on 409 conflict, surfaces a clear error and refreshes the claim state

4. **CorrectionBanner** (amber) — appears when the request was previously returned (by bank or by support) and re-submitted. Shows the original return reason as historical context for the current reviewer.

5. **LockedBanner** (gray; red variant for terminal) — appears for terminal states (`SUPPORT_REJECTED`, `SUPPORT_APPROVED` finalized, `EXECUTIVE_*`, `COMPLETED`).

---

**WorkflowProgress:** standard horizontal stage progress bar.

---

**Tabs:**

| Tab            | Purpose                                                                  |
| -------------- | ------------------------------------------------------------------------ |
| المعلومات      | Request and supplier/shipment fields, read-only                          |
| الوثائق        | DocumentChecklist scoped to intake-stage documents (download enabled)    |
| الأطراف        | Actors so far                                                            |
| السجل          | Business-readable stage history with claim acquire/release events visible |

The History tab makes claim events visible (e.g. "تمت المطالبة بالطلب من قبل [name] في [timestamp]"; "تم إطلاق المطالبة تلقائياً بسبب انتهاء المهلة في [timestamp]").

---

**Overview tab:** read-only request data. The reviewer reads carefully and compares with the document uploads on the next tab.

**Documents tab:** DocumentChecklist showing intake documents only. SWIFT, FX request, and external FX confirmation rows are not listed at all — they don't exist at this stage and would be misleading. (Compare with Bank Reviewer's Documents tab, which surfaces locked rows for downstream documents — that's appropriate for the bank reviewer but not for support.)

**Parties tab:** Submitter (Data Entry), Bank Reviewer who approved, Support claimant (if any).

**Activity log tab:** stage history including claim acquire/release events. Auto-transitions are labeled.

---

**ActionsPanel (right column):**

Composition is strictly claim-state-dependent.

**Status = `SUPPORT_REVIEW_PENDING` (unclaimed):**

- "مطالبة بهذا الطلب" (primary indigo, large) — atomic claim; transitions to `SUPPORT_REVIEW_IN_PROGRESS`. On 409 conflict (another member claimed first), surfaces an error inline and refreshes the page state.

**Status = `SUPPORT_REVIEW_IN_PROGRESS` (claimed by me):**

- "اعتماد الطلب" (primary green) — transitions to `SUPPORT_APPROVED`; auto-chains to `EXECUTIVE_VOTING_OPEN`. Confirmation modal:
  - Title: "اعتماد الطلب وفتح التصويت التنفيذي"
  - Body: short summary (reference, bank, amount) + reminder that approval auto-opens executive voting
  - Optional reviewer note textarea (not mandatory for approval)
  - Buttons: Cancel / Approve (primary green)
- "إعادة إلى المدخل للتعديل" (outline amber) — return dialog requiring mandatory reason (min 10 chars). Transitions to `SUPPORT_RETURNED`. Modal includes an optional list of specific fields/documents the reviewer wants the bank's Data Entry to correct.
- "رفض الطلب" (destructive red) — hard confirmation dialog:
  - Title: "رفض الطلب"
  - Warning: "هذا الإجراء يُعيد الطلب إلى مراجع البنك ليقرر إعادة فتحه للتعديل أو إبقاء الرفض."
  - Mandatory reason textarea (min 20 chars)
  - Buttons: Cancel / Reject (destructive)
- "إطلاق المطالبة" (ghost) — releases the claim without deciding; transitions back to `SUPPORT_REVIEW_PENDING`. Confirmation dialog: "هل أنت متأكد من إطلاق المطالبة؟ سيعود الطلب إلى طابور المساندة."

**Status = `SUPPORT_REVIEW_IN_PROGRESS` (claimed by others):**

- No decision buttons (hidden, not disabled)
- A small information note: "هذا الطلب محجوز حالياً لـ[reviewer name]. سيظهر للمطالبة من جديد إذا تم إطلاقه أو انتهت مهلته."

**Status = terminal:** empty panel; LockedBanner at top provides context.

---

**Claim expiry behavior (background):**

If the heartbeat lapses while the reviewer is mid-decision (e.g. they walked away from the desk), the claim auto-releases after 15 minutes:

- The next API call from the page (decision attempt, additional heartbeat) returns 409 with a `CLAIM_EXPIRED` code
- The page surfaces a modal: "انتهت مهلة المطالبة بسبب عدم النشاط. عاد الطلب إلى الطابور."
- Modal offers: "العودة للطابور" (primary) or "إعادة المطالبة" (secondary, attempts a new atomic claim — succeeds only if no one else claimed in the meantime)
- A `CLAIM_RELEASED` notification is also delivered to the user's notification stream

---

### Print Request (`/requests/[id]/print`)

Same print-optimized A4 layout as DATA_ENTRY's print view, with the support reviewer's name appended in the footer when applicable.

---

### Notifications (`/notifications`)

The Support Committee member receives notifications for:

- New request entering the support queue (`SUPPORT_REVIEW_PENDING`) — informational, opt-in (otherwise noise)
- My claim was auto-released by TTL expiry (`CLAIM_RELEASED`) — highest priority; opt-in default ON
- A claim I held was released and another member took it (informational)
- Decision outcome confirmations (my own approve/return/reject succeeded)
- Aging-claim reminder if my claim is approaching TTL expiry (optional, per preference)

Voting tallies, SWIFT events, FX completion, and bank-side events are not delivered to this role.

Page structure is identical to `data-entry.md`.

---

### Settings (`/settings`)

Same 6-tab layout as `data-entry.md`. Notification preferences default to:

- Claim release (auto-expiry): ON, high priority
- New request in queue: OFF (opt-in; otherwise noisy)
- Decision-outcome confirmation: ON
- Aging-claim reminder: OFF (opt-in)

---

### Profile (`/profile`)

Same 3-column layout as `data-entry.md`. Center stats highlight: total reviews performed, approvals, returns, rejections, average claim duration (own performance).

---

## Forbidden Actions Reference

SUPPORT_COMMITTEE cannot, under any UI condition:

- Decide on a request they have not claimed (decision buttons hidden, not disabled)
- Hold more than one active claim that exceeds the heartbeat-bound concurrent-claim policy (frontend may surface a soft warning; backend enforcement may apply)
- Override another member's active claim
- Approve, reject, or vote at the executive stage
- Open, close, or finalize executive voting sessions
- Upload SWIFT, FX confirmation request, or external FX confirmation documents
- Download SWIFT, FX request, or external FX confirmation documents
- Access bank-side staff or merchant management surfaces
- Create or edit request business data
- Reverse a finalized support decision (`SUPPORT_APPROVED`, `SUPPORT_RETURNED`, `SUPPORT_REJECTED` are terminal for this role)
- Bypass the claim mechanism (decision endpoints reject any attempt without a valid Redis claim key)

The UI must not render any of the above controls.

---

## Cross-Role Handoffs

SUPPORT_COMMITTEE sits at the **bank-to-CBY-decisioning boundary**. Three handoff directions exist:

1. **BANK_REVIEWER → SUPPORT_COMMITTEE (on bank approval, auto):** request auto-transitions from `BANK_APPROVED` to `SUPPORT_REVIEW_PENDING` and enters the global queue. Active committee members may receive a notification (per preference).
2. **SUPPORT_COMMITTEE → DATA_ENTRY (on return, `SUPPORT_RETURNED`):** the request goes back to the bank's intake for correction. The reason text becomes the primary correction guidance for the requester.
3. **SUPPORT_COMMITTEE → BANK_REVIEWER (on rejection, `SUPPORT_REJECTED`):** the request returns to the bank reviewer's queue for the keep-or-return decision (the bank reviewer has the final bank-side authority to terminally close or re-route).
4. **SUPPORT_COMMITTEE → EXECUTIVE_MEMBER (on approval, auto):** approval transitions to `SUPPORT_APPROVED` and the workflow auto-chains to `EXECUTIVE_VOTING_OPEN`. All active executive members are notified and may cast votes.

Claim ownership matters across handoffs because the audit trail records *which committee member made the decision*, not just "the support committee".

---

## UX Principles

- Claim state is the page's central truth. It must be unambiguous on the queue, on the request detail, and in notifications.
- "Mine", "others'", and "unclaimed" are visually distinct with semantic color (indigo / gray / amber). Color alone is not the only signal — labels and chips reinforce.
- Active claims are the highest-attention surface on the dashboard. A forgotten claim blocks throughput for up to 15 minutes per request.
- The heartbeat indicator on the active-review banner reassures the reviewer that their claim is alive. A failing heartbeat must be surfaced, not silent.
- Decision buttons are hidden — not disabled — for requests the user has not claimed. Disabled buttons suggest "you could do this if X"; hidden buttons say "this is not your work".
- Every consequential decision (approve, return, reject) requires an explicit, audited reason. Reason length minimums apply.
- Approval is consequential: the auto-chain immediately opens executive voting. The confirmation modal must remind the reviewer of this downstream effect.
- Rejection is not terminal at this stage — it returns to bank reviewer. The confirmation dialog must clarify the downstream path so the reviewer knows what happens next.
- Returns must feel like correction guidance, not punishment. Reasons are presented to the bank's Data Entry verbatim.
- Claim expiry is communicated immediately on the next interaction — never silently rolling back the user's work. A "re-claim" path is offered.
- The queue's "إخفاء المحجوز للآخرين" toggle is an operational helper that reduces noise when scanning for available work — small detail, big workflow value.
- Notifications default to operationally important events only. "New request in queue" defaults OFF to prevent claim-rush bidding noise.

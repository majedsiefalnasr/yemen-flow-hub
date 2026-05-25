# EXECUTIVE_MEMBER — Executive Committee Member

Arabic label: عضو اللجنة التنفيذية

---

## Role Identity

The executive decision participant. After the Support Committee approves a request (`SUPPORT_APPROVED`), the workflow **auto-opens** an executive voting session (`EXECUTIVE_VOTING_OPEN`). Each active executive member casts exactly one vote per session. Voting remains open until the Committee Director closes it, and the Director cannot close it until **every active executive member has cast a vote**.

EXECUTIVE_MEMBER is a **CBY-global governance role focused on voting**. The role has no operational authority — it does not open sessions, does not close sessions, does not finalize decisions, does not resolve ties, does not upload SWIFT, and does not complete external FX confirmations. Its singular workflow act is casting a binary decision (approve / reject) on each open session.

The role does have broad cross-bank read access, including post-approval SWIFT and FX request documents, because executive members may need that context for governance reporting and audit awareness — but they do not act on those stages.

---

## Operational Posture

| Aspect              | Tone                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------- |
| Work mode           | Governance voting participant — narrow but high-authority                             |
| Primary surface     | Active voting sessions (`EXECUTIVE_VOTING_OPEN` where I have not yet voted)           |
| Secondary surface   | Cross-bank governance visibility — reports, finalized decisions                       |
| Tertiary surface    | Reports (cross-bank scope) for governance review                                       |
| Status language     | Full canonical CBY labels                                                              |
| Visual density      | Medium: document-heavy review surface, voting tally visibility                        |
| Decision tone       | Calm, deliberate, governance-grade. Votes are individual and recorded by name         |
| Visibility tone     | Cross-bank read access; oversight-aware but not investigative                          |

---

## Scope & Boundary

- **Organization scope:** All banks (CBY-global). The member sees requests originating from any bank.
- **Voting scope:** One vote per session. The vote is binding once cast and cannot be modified by the member (only the Director may override via tie-break with mandatory reason; the original member vote remains in the audit record).
- **Stage scope:** Voting authority is limited to `EXECUTIVE_VOTING_OPEN`. Earlier stages are read-only context; later stages (`EXECUTIVE_VOTING_CLOSED` onward) are read-only monitoring.
- **Concurrency:** Vote submission is atomic and protected by pessimistic locking (`SELECT ... FOR UPDATE` on the voting session). Double-submit attempts are rejected.
- **Race condition:** If the Director closes the session between the member opening the page and submitting the vote, the submit returns `VOTING_SESSION_CLOSED` (HTTP 409). The UI rolls back the optimistic update and surfaces a clear notification.

---

## Workflow Authority Summary

| Stage                              | EXECUTIVE_MEMBER authority                                                |
| ---------------------------------- | ------------------------------------------------------------------------- |
| `DRAFT` through `SUPPORT_APPROVED` | Read-only — request not yet in executive scope                            |
| `WAITING_FOR_VOTING_OPEN`          | Read-only context; voting auto-opens on next system tick                  |
| `EXECUTIVE_VOTING_OPEN` (not yet voted) | Cast vote (approve / reject)                                         |
| `EXECUTIVE_VOTING_OPEN` (already voted) | Read-only; cannot change vote                                        |
| `EXECUTIVE_VOTING_CLOSED`          | Read-only; awaiting Director finalization                                 |
| `EXECUTIVE_APPROVED` / `EXECUTIVE_REJECTED` | Read-only; final decision                                          |
| `WAITING_FOR_SWIFT` through `COMPLETED` | Read-only monitoring (cross-bank visibility)                          |
| All rejection/terminal states      | Read-only                                                                 |

The role explicitly cannot:

- Open a voting session manually (sessions open automatically after support approval)
- Close a voting session (Director-only)
- Finalize a decision (Director-only)
- Override or resolve a tie (Director-only)
- Vote on behalf of another member
- Modify a vote already cast

---

## Document Authority

| Document                            | Access                |
| ----------------------------------- | --------------------- |
| Request documents (intake)          | View + Download / All banks |
| SWIFT document                      | View + Download / All banks |
| FX confirmation request document    | View + Download / All banks |
| External FX confirmation PDF        | No                    |

The external FX confirmation PDF is reserved for the Committee Director (who completes it) and the Bank Reviewer (as the bank-side record-keeper). Executive members can see the SWIFT and FX request documents for governance context but do not handle the signed/stamped artifact.

---

## Sidebar Navigation

| Group                    | Item                          | Route          |
| ------------------------ | ----------------------------- | -------------- |
| الرئيسية (Main)          | اللوحة الرئيسية (Dashboard)   | /dashboard     |
| الرئيسية                 | طلبات التمويل (Requests)      | /requests      |
| الرئيسية                 | الإشعارات (Notifications)     | /notifications |
| الإدارة (Administration) | التقارير والتحليلات (Reports) | /reports       |
| الأخرى (Other)           | الإعدادات (Settings)          | /settings      |

No Operations group (the member does not initiate work). The Reports surface is included because governance review benefits from cross-bank analytical context.

---

## Pages

### Login (`/login`)

Identical to the login page in `data-entry.md`. MFA is strongly recommended for executive members (governance role); a notice may appear during login if MFA is not yet enrolled.

---

### Dashboard (`/dashboard`)

The Executive Member dashboard is a **voting workload console**. Its job is to answer two questions in less than three seconds:

1. Are there voting sessions waiting for **my** vote?
2. Is the broader voting workload aging in a way that requires attention?

The dashboard does not include claim controls (not this role), SWIFT controls (not this role), or finalization controls (Director-only). It does include enough cross-bank visibility for the member to feel oriented to the platform's executive pipeline.

---

**Page header:**

- Greeting: "أهلاً، [first name]"
- Subtitle: "عضو اللجنة التنفيذية"
- Read-only chip: "نطاق عبر البنوك"

---

**Action-required strip (conditional, highest priority):**

If the current user has one or more sessions in `EXECUTIVE_VOTING_OPEN` where they have not yet voted, a strip appears beneath the header:

- Icon: Indigo Vote (or check-box pending)
- Title: "[N] جلسات تصويت تنتظر صوتك"
- Subtitle: shows the oldest pending session's reference + how long it has been waiting for this member
- Action: "ابدأ التصويت" → opens the oldest pending session's detail page

This is the highest-priority surface because the Director cannot close a session until every active member has voted. A single delayed member blocks the entire decision.

---

**KPI cards (3-column grid):**

| Card                                       | Source                    | Color                                | Click-through                              |
| ------------------------------------------ | ------------------------- | ------------------------------------ | ------------------------------------------ |
| طابور التصويت (My Voting Queue)            | `pending_my_vote`         | Indigo (left-border highlight when > 0) | `/requests?tab=pending_my_vote`         |
| قرارات اعتماد (Approval Decisions)         | `decisions_approved`      | Green                                | `/requests?tab=approved`                   |
| قرارات رفض (Rejection Decisions)           | `decisions_rejected`      | Rose                                 | `/requests?tab=rejected`                   |

Counts are scoped to decisions where this member participated. The first card represents work the member must do; the others represent historical outcomes.

---

**Voting queue table:**

Shows requests currently in executive voting stages (`SUPPORT_APPROVED`, `WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`), sorted by:

1. `EXECUTIVE_VOTING_OPEN` where I have not voted (oldest first)
2. `EXECUTIVE_VOTING_OPEN` where I have voted (most recent first)
3. `EXECUTIVE_VOTING_CLOSED` (awaiting finalization)
4. `SUPPORT_APPROVED` / `WAITING_FOR_VOTING_OPEN` (about to open)

| Column           | Description                                                                              |
| ---------------- | ---------------------------------------------------------------------------------------- |
| Reference Number | Monospace                                                                                |
| Bank             | Originating bank                                                                         |
| Supplier         | Supplier name (intake data)                                                              |
| Amount           | Formatted amount + currency, right-aligned                                               |
| Status           | Status badge (indigo for voting stages)                                                  |
| My Vote          | "لم تصوّت بعد" (indigo chip, action-required); "اعتمدت" (green); "رفضت" (rose); "—" if not yet open |
| Voting Progress  | "X/Y صوتوا" indicator with a thin progress bar                                            |
| Age in Stage     | Time since session opened                                                                |
| Actions          | "تصويت" (primary indigo) if my vote pending; "عرض" otherwise                              |

Rows where `EXECUTIVE_VOTING_OPEN` and the member has not voted are visually emphasized (indigo left-border or tinted row background) — these are the most actionable rows.

---

**States:** skeleton on load; "لا توجد جلسات تصويت نشطة حالياً ✓" on empty (reassuring); inline error with retry.

---

### Requests List (`/requests`)

Scope: all banks. The list is the cross-bank executive-stage registry plus historical decisions.

**Page header:**

- Title: "طلبات تمويل الواردات"
- Subtitle: "جميع الطلبات في مرحلة التصويت التنفيذي والمراحل اللاحقة"
- Breadcrumbs: الرئيسية → طلبات التمويل

---

**Stage tabs (voting-aware):**

| Tab key              | Label                       | Mapped statuses                                                       |
| -------------------- | --------------------------- | --------------------------------------------------------------------- |
| `pending_my_vote`    | يحتاج صوتي                  | `EXECUTIVE_VOTING_OPEN` where I have not voted                        |
| `voted_by_me`        | صوّتُّ عليها                | `EXECUTIVE_VOTING_OPEN` / `EXECUTIVE_VOTING_CLOSED` / `EXECUTIVE_APPROVED` / `EXECUTIVE_REJECTED` where I voted |
| `pending_open`       | بانتظار فتح التصويت         | `SUPPORT_APPROVED`, `WAITING_FOR_VOTING_OPEN`                         |
| `voting_open`        | التصويت مفتوح               | `EXECUTIVE_VOTING_OPEN`                                               |
| `voting_closed`      | التصويت مغلق                | `EXECUTIVE_VOTING_CLOSED`                                             |
| `approved`           | معتمد                       | `EXECUTIVE_APPROVED`                                                  |
| `rejected`           | مرفوض                       | `EXECUTIVE_REJECTED`                                                  |
| `post_approval`      | ما بعد الاعتماد             | `WAITING_FOR_SWIFT`, `SWIFT_UPLOADED`, `FX_CONFIRMATION_PENDING`, `COMPLETED` |
| `all`                | الكل                        | All executive-relevant statuses                                       |

The `pending_my_vote` tab is intentionally first.

---

**Toolbar:**

- Search input (reference, supplier, merchant, invoice number, bank)
- Bank filter dropdown: "جميع البنوك" default; one entry per bank
- Customize Columns dropdown
- Export button (CSV; filter-scoped)
- Refresh button

No bulk-vote action. Each vote is individual, named, and recorded.

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
| My Vote          | Same conventions as the dashboard table                                                  |
| Voting Progress  | Tally indicator with member-pending count                                                |
| Age in Stage     | Time since session opened; turns amber after configured warning threshold                |
| Actions          | "تصويت" or "عرض" depending on my vote state                                              |

Row click → request detail.

---

### Request Detail (`/requests/[id]`)

The Executive Member's primary working surface for cast votes. The page surfaces the request data, document context, and the inline VotingPanel.

**Page header:**

- Title: Request reference (monospace)
- Breadcrumbs: الرئيسية → طلبات التمويل → [Reference]
- Status badge (full canonical label)
- Print button → `/requests/[id]/print`

---

**Context banners:**

1. **VotingPendingBanner** (indigo) — appears when `EXECUTIVE_VOTING_OPEN` and the current user has not yet voted. Title: "صوتك مطلوب على هذه الجلسة." Subtitle: tally so far; remaining members count.
2. **VotedConfirmationBanner** (gray, subtle) — appears after the member has cast a vote on this session. Shows: "صوّتت [وقت] — [اعتمدت / رفضت]." No CTA. The vote is final.
3. **LockedBanner** (gray; red variant for `EXECUTIVE_REJECTED`) — for terminal states.

No CorrectionBanner, no claim banners — those are other roles' surfaces.

---

**WorkflowProgress:** standard horizontal stage progress bar.

---

**VotingPanel (inline, above the tabs):**

The VotingPanel is mounted when the request status ∈ {`WAITING_FOR_VOTING_OPEN`, `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`, `EXECUTIVE_APPROVED`, `EXECUTIVE_REJECTED`} AND the viewer is an Executive Member or Committee Director.

**Panel composition:**

- **Session header:** "جلسة التصويت التنفيذي" with status chip ("مفتوحة" / "مغلقة" / "اعتمدت" / "رفضت") and session opened timestamp
- **Tally display:** three colored pills side by side — "اعتماد: [count]" (green), "رفض: [count]" (rose), "لم يصوّت: [count]" (gray)
- **Member vote list:** every active executive member shown with their name, role chip, and per-member vote status. Visibility of the specific vote choice depends on session state and platform policy:
  - During `EXECUTIVE_VOTING_OPEN`: show "صوّت" (yes/no badge) or "لم يصوّت بعد" — specific choices may be masked to prevent vote influence (configurable per platform policy; default = masked)
  - After `EXECUTIVE_VOTING_CLOSED` and finalized: specific choices are visible to executive members + Director
- **Vote buttons** (rendered only when status = `EXECUTIVE_VOTING_OPEN` AND current user has not voted):
  - "اعتماد" — primary green button, large
  - "رفض" — destructive red button, large
  - Optional reason textarea (always optional for approve; recommended-but-optional for reject — platform policy may make this mandatory)
- **Voted state:** after submission, vote buttons are replaced with a confirmation chip: "لقد صوّتت — [اعتمدت / رفضت]" + small timestamp. The vote cannot be changed.
- **Tie-break placeholder:** if vote totals are tied at session close, a gray informational chip reads "تعادل النتائج — سيتولى مدير اللجنة التنفيذية الحسم." Executive members do not have the tie-break authority; they wait for the Director.
- **Closed/finalized state:** the panel transitions to a summary card showing the final tally, the Director's finalization timestamp, and the outcome.

---

**Tabs:**

| Tab            | Purpose                                                                                |
| -------------- | -------------------------------------------------------------------------------------- |
| المعلومات      | Request and supplier/shipment fields, read-only                                        |
| الوثائق        | DocumentChecklist — request documents, SWIFT, FX request (downloadable)                |
| الأطراف        | Actors so far                                                                          |
| التصويت        | Detailed vote-by-member breakdown (visibility per platform policy)                     |
| السجل          | Business-readable stage history including auto-open and finalize events                |

---

**Overview tab:** read-only request data. The member reviews the financial and supplier facts before voting.

**Documents tab:** DocumentChecklist. Executive Member downloads available for: request documents (all banks), SWIFT document (all banks, when uploaded), FX confirmation request document (all banks, when uploaded). The external FX confirmation PDF row is shown as locked with a tooltip ("مخصص لمدير اللجنة التنفيذية ومراجعي البنك."). SWIFT and FX request rows only become populated after `EXECUTIVE_APPROVED` and after the SWIFT Officer uploads them.

**Parties tab:** all actors so far.

**Voting tab (new — distinct from the inline panel):** detailed breakdown of each member's vote with timestamps and optional reasons (subject to platform visibility policy). After finalization, the Director's finalize record is also shown.

**Activity log tab:** stage history. Auto-transitions ("فتح التصويت تلقائياً بعد اعتماد المساندة"; "نقل تلقائي إلى مرحلة السويفت بعد اعتماد اللجنة التنفيذية") are labeled.

---

**ActionsPanel (right column):**

The ActionsPanel is intentionally minimal for this role — voting happens inline in the VotingPanel, not in the right rail.

- Status = `EXECUTIVE_VOTING_OPEN`, not voted: a single reminder card "صوتك مطلوب أعلاه" with an anchor-link button that scrolls to the VotingPanel
- Status = `EXECUTIVE_VOTING_OPEN`, already voted: a small "صوّتت بـ [choice]" confirmation chip; no other actions
- Status = `EXECUTIVE_VOTING_CLOSED`: informational note "بانتظار الإصدار النهائي من مدير اللجنة التنفيذية."
- Status = finalized or terminal: empty panel; LockedBanner at top provides context

The right rail must not contain vote buttons (they live in the inline panel) and must not contain close/finalize controls (those are Director-only).

---

**Vote submission behavior:**

- The member clicks "اعتماد" or "رفض"
- The UI optimistically updates the tally pill and replaces the vote buttons with the confirmation chip
- The backend processes atomically; on success, the optimistic state is confirmed
- On `VOTING_SESSION_CLOSED` (409): the optimistic update is rolled back; a clear notification surfaces ("أُغلقت جلسة التصويت قبل وصول صوتك. لا يمكن تسجيل الصوت."); the page transitions to read-only state
- On other 4xx errors: the optimistic update is rolled back; the error is surfaced with a retry option where applicable

The member cannot resubmit or change their vote after a successful submission. If they navigate away and back, the confirmation chip remains.

---

### Print Request (`/requests/[id]/print`)

Same print-optimized A4 layout as DATA_ENTRY's print view, with a voting tally summary card at the bottom when the request has reached voting stages.

---

### Reports (`/reports`)

Executive Members have access to a cross-bank reports surface, scoped to their governance role:

**Page header:**

- Title: "التقارير والتحليلات"
- Subtitle: "تحليل عبر البنوك مخصص لأعضاء اللجنة التنفيذية"
- Breadcrumbs: الرئيسية → التقارير
- Export buttons: CSV, PDF
- Date range filter

**KPI strip (5 cards):** Total Requests, Financing Value, Approval Rate (executive-stage), Voting Participation Rate (this member's vote participation), Average Voting Duration.

**Charts (2-column grid):**

- Monthly trend (line) — submissions, approvals, rejections across all banks
- Category distribution (donut) — by goods type
- Amount by currency (bar) — total financed value
- Voting participation heatmap — this member's voting timing distribution

**Voting analytics section:**

A compact table showing this member's voting record over the period: sessions, votes cast, approval %, average time-to-vote. This is governance self-awareness data, not a performance evaluation.

The Reports page is intentionally read-only — no schedule, no broadcast (those belong to CBY Admin and Director scopes).

---

### Notifications (`/notifications`)

The Executive Member receives notifications for:

- New voting session opened (`EXECUTIVE_VOTING_OPEN` auto-transition) — highest priority
- Reminder if a session has been open ≥ configured threshold and the member has not voted
- Voting session closed by Director
- Final decision finalized (`EXECUTIVE_APPROVED` / `EXECUTIVE_REJECTED`)
- High-value request alert (if amount exceeds configured governance threshold)

Claim transfers, SWIFT events, FX completion, and bank-side operational events are not delivered to this role.

Page structure is identical to `data-entry.md`.

---

### Settings (`/settings`)

Same 6-tab layout as `data-entry.md`. Notification preferences default to all voting-relevant events enabled. The Security tab strongly prompts MFA enrollment for governance roles.

---

### Profile (`/profile`)

Same 3-column layout as `data-entry.md`. Center stats highlight: sessions participated, average time-to-vote, approval %. The MFA status card is visually elevated for governance roles.

---

## Forbidden Actions Reference

EXECUTIVE_MEMBER cannot, under any UI condition:

- Manually open a voting session (sessions open automatically after support approval)
- Close a voting session (Director-only)
- Finalize an executive decision (Director-only)
- Resolve a tie scenario (Director-only)
- Vote more than once per session
- Change a vote after submission
- Vote on behalf of another member
- Upload SWIFT, FX confirmation request, or external FX confirmation documents
- Download the external FX confirmation PDF
- Generate or re-upload the signed external FX confirmation PDF
- Claim or release support reviews
- Approve, return, or reject at the bank or support stages
- Hold the `COMMITTEE_DIRECTOR` role simultaneously (the same user cannot hold both)
- Modify request business data
- Access bank-side staff or merchant management surfaces

The UI must not render any of the above controls.

---

## Cross-Role Handoffs

EXECUTIVE_MEMBER participates in two handoff points:

1. **SUPPORT_COMMITTEE → EXECUTIVE_MEMBER (auto on `SUPPORT_APPROVED`):** the workflow auto-opens a voting session as `EXECUTIVE_VOTING_OPEN`. All active executive members are notified.
2. **EXECUTIVE_MEMBER → COMMITTEE_DIRECTOR (on full participation):** once every active member has voted, the Director can close the session. The member's own act is complete at vote submission; the Director closes and finalizes downstream.

The role has no other handoffs. It does not initiate work and does not own downstream stages.

---

## UX Principles

- The voting panel is the soul of this role. It must be inline, above the tabs, and inviting to act on.
- The "صوتك مطلوب" state (action required for this member) is the highest-attention element across the dashboard, the queue, the requests list, and the request detail. A single delayed vote blocks the entire decision.
- Vote submission must feel weighty but not bureaucratic. The buttons are large; reason textareas are present but mostly optional; the confirmation is clear and final.
- Vote choice visibility during an active session is masked by default. Members should not see each other's votes until the session closes, to prevent influence and groupthink.
- Vote tallies are presented as colored pills (green/red/gray), readable at a glance, not as long text.
- The tie-break placeholder makes the Director's role explicit when tallies are tied. Executive members must not be confused about who resolves a tie.
- Optimistic updates make voting feel instant. The race-condition rollback (`VOTING_SESSION_CLOSED`) must be communicated clearly, never silent.
- Decision buttons disappear after submission — replaced with a confirmation chip. There must be no ambiguity that the vote is final and unchangeable.
- The ActionsPanel is intentionally minimal. The work happens in the inline VotingPanel; the right rail must not duplicate or confuse with voting controls.
- Cross-bank visibility is for governance review, not investigation. The role does not get audit, voting forensics, or bank-comparison tools that belong to CBY Admin.
- MFA enrollment is strongly recommended for governance roles. The Profile and Settings surfaces elevate MFA status as a security signal.

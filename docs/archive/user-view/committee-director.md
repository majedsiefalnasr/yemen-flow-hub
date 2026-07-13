# COMMITTEE_DIRECTOR — Executive Committee Director

Arabic label: مدير اللجنة التنفيذية

---

## Role Identity

The executive chair and the **final workflow authority** in Yemen Flow Hub. The Director participates in executive voting alongside members, but holds three exclusive responsibilities no other role can perform:

1. **Closing executive voting sessions** — only after every active executive member has voted.
2. **Finalizing the executive decision** — translating the closed session into `EXECUTIVE_APPROVED` or `EXECUTIVE_REJECTED`, including the tie-break/override pathway with mandatory reason.
3. **Completing the external FX confirmation workflow** — downloading the system-generated PDF, signing/stamping it externally, and re-uploading the signed copy to transition the request to `COMPLETED`.

COMMITTEE_DIRECTOR is a **CBY-global governance and completion role**. The role does not open voting sessions manually (they auto-open after support approval), does not upload SWIFT documents (that is the bank's SWIFT Officer), does not act as a generic super-admin, and is not the same identity as `EXECUTIVE_MEMBER` (the same user must not hold both roles simultaneously). The role is the **single point of completion** for every approved request — operational continuity at this role is critical to the platform.

---

## Operational Posture

| Aspect              | Tone                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------- |
| Work mode           | Governance closer + final-completion authority                                        |
| Primary surface     | Active voting sessions ready to close + FX-confirmation pending queue                 |
| Secondary surface   | Cross-bank governance visibility — voting analytics, audit, reports                   |
| Tertiary surface    | Read-only oversight of all post-approval activity                                     |
| Status language     | Full canonical CBY labels                                                              |
| Visual density      | Medium-high: voting tally + member-status visibility + dual queues (voting + FX)      |
| Decision tone       | Calm, deliberate, governance-grade. Tie-break and override always require reason       |
| Completion tone     | Procedural — external sign/stamp loop is structured and audit-aware                    |

---

## Scope & Boundary

- **Organization scope:** All banks (CBY-global). Full cross-bank visibility on executive and FX stages.
- **Voting authority:** Casts one vote per session like other members; additionally closes and finalizes the session.
- **Tie-break authority:** When member votes are tied, the Director resolves with a mandatory reason. The override is recorded distinctly from member votes in the audit trail.
- **FX completion authority:** Exclusive ownership of the external FX confirmation workflow — download generated PDF, externally sign/stamp, re-upload signed copy. The full lifecycle is one atomic transaction.
- **Role exclusivity:** Cannot simultaneously hold `EXECUTIVE_MEMBER`. Backend (and CBY Admin's staff page) enforces this; if a user is assigned Director, their executive member assignment is removed (with explicit confirmation in the admin UI).

---

## Workflow Authority Summary

| Stage                              | COMMITTEE_DIRECTOR authority                                                         |
| ---------------------------------- | ------------------------------------------------------------------------------------ |
| `DRAFT` through `SUPPORT_APPROVED` | Read-only — request not yet in director scope                                        |
| `WAITING_FOR_VOTING_OPEN`          | Read-only context; voting auto-opens on next system tick                              |
| `EXECUTIVE_VOTING_OPEN`            | Cast own vote (as a member); monitor session progress; **cannot close until all members have voted** |
| `EXECUTIVE_VOTING_OPEN` (all voted) | **Close voting session** (transitions to `EXECUTIVE_VOTING_CLOSED`)                  |
| `EXECUTIVE_VOTING_CLOSED`          | **Finalize decision** (transitions to `EXECUTIVE_APPROVED` or `EXECUTIVE_REJECTED`); tie-break with mandatory reason if tally is tied |
| `WAITING_FOR_SWIFT` / `SWIFT_UPLOADED` | Read-only monitoring; SWIFT belongs to bank's SWIFT Officer                       |
| `FX_CONFIRMATION_PENDING`          | **Download generated FX confirmation PDF**; **Upload signed/stamped copy**; **Complete the request** (transitions to `COMPLETED`) |
| `CUSTOMS_DECLARATION_ISSUED` (legacy) | Read-only; complete via signed upload                                             |
| `COMPLETED`                        | Read-only; access to signed external FX confirmation document                         |
| All rejection/terminal states      | Read-only                                                                            |

**Critical rule:** the Director must not be allowed to close a voting session if any active executive member has not voted. The UI surfaces this as a disabled button with a specific tooltip identifying which members still need to vote — never a silent block.

**Critical rule:** the FX completion sequence (download → external sign → re-upload) must be wrapped in a single backend transaction with row-level locking to prevent half-completed states.

---

## Document Authority

| Document                            | Access                |
| ----------------------------------- | --------------------- |
| Request documents (intake)          | View + Download / All banks |
| SWIFT document                      | View + Download / All banks |
| FX confirmation request document    | View + Download / All banks |
| External FX confirmation PDF (generated, unsigned) | Generate + Download / All banks |
| External FX confirmation PDF (signed, re-uploaded) | View + Download / All banks |

The Director holds the broadest document authority on the platform alongside CBY Admin. The signed external FX confirmation PDF is the role's most consequential artifact — it is the final compliance evidence for an approved request.

---

## Sidebar Navigation

| Group                    | Item                                          | Route          |
| ------------------------ | --------------------------------------------- | -------------- |
| الرئيسية (Main)          | اللوحة الرئيسية (Dashboard)                   | /dashboard     |
| الرئيسية                 | طلبات التمويل (Requests)                      | /requests      |
| الرئيسية                 | الإشعارات (Notifications)                     | /notifications |
| العمليات (Operations)    | تأكيد المصارفة الخارجية (External FX Confirmation) | /customs   |
| الإدارة (Administration) | التقارير والتحليلات (Reports)                 | /reports       |
| الإدارة                  | التدقيق والامتثال (Audit)                     | /audit         |
| الأخرى (Other)           | الإعدادات (Settings)                          | /settings      |

The Operations group contains the External FX Confirmation queue, which is unique to this role. The route name `/customs` is retained as legacy URL alias; the label and content reflect the updated "external FX confirmation" terminology.

---

## Pages

### Login (`/login`)

Identical to the login page in `data-entry.md`. MFA is **required** for the Director role; backend may block unauthenticated access if MFA is not enrolled.

---

### Dashboard (`/dashboard`)

The Committee Director dashboard is a **governance and completion console**. Its job is to answer four questions in less than five seconds:

1. Are there voting sessions ready to close (all members have voted)?
2. Are there closed sessions ready for finalization?
3. Are there requests waiting for external FX confirmation completion?
4. Is my own voting workload current?

The dashboard surfaces dual queues — voting lifecycle and FX completion — because both are Director-exclusive bottlenecks. Aging in either queue directly stalls platform throughput.

---

**Page header:**

- Greeting: "أهلاً، [first name]"
- Subtitle: "مدير اللجنة التنفيذية"
- Read-only chip: "نطاق عبر البنوك"
- Toolbar (right): Refresh, Last-updated timestamp, Export Director Summary PDF

---

**Action-required strip (conditional, highest priority — composite):**

A single composite strip appears beneath the header when ANY of the following Director-exclusive actions are pending:

- **N voting sessions ready to close** (`EXECUTIVE_VOTING_OPEN` with all members voted)
- **N closed sessions ready to finalize** (`EXECUTIVE_VOTING_CLOSED`)
- **N FX confirmations pending signed upload** (`FX_CONFIRMATION_PENDING`)
- **N sessions with tied tallies awaiting tie-break**

The strip is grouped into up to three sub-rows when multiple categories have pending items, each with its own counter and direct action button. Examples:

- "[3] جلسات تصويت جاهزة للإغلاق" → `/requests?tab=ready_to_close`
- "[2] قرارات بانتظار الإصدار النهائي" → `/requests?tab=ready_to_finalize`
- "[5] تأكيدات مصارفة بانتظار التوقيع والرفع" → `/customs`

If all categories are empty, the strip is hidden entirely.

---

**KPI cards (4-column grid):**

| Card                                       | Source                       | Color                                | Click-through                              |
| ------------------------------------------ | ---------------------------- | ------------------------------------ | ------------------------------------------ |
| جلسات تصويت نشطة (Active Voting Sessions)  | `active_voting_sessions`     | Indigo (left-border highlight when > 0) | `/requests?tab=voting_open`             |
| تأكيد مصارفة معلق (FX Confirmation Pending)| `fx_confirmation_pending`    | Amber (left-border highlight when > 0) | `/customs`                              |
| قرارات نهائية (Finalized Decisions)        | `finalized_decisions`        | Green                                | `/requests?tab=approved`                   |
| قرارات رفض (Rejection Decisions)           | `decisions_rejected`         | Rose                                 | `/requests?tab=rejected`                   |

Counts are real-time. Click-through opens the requests/customs queue pre-filtered.

---

**Voting lifecycle table (the Director's voting workload):**

Shows sessions in `EXECUTIVE_VOTING_OPEN` and `EXECUTIVE_VOTING_CLOSED`. Sorted by:

1. Sessions ready to close (all members voted) — highest priority
2. Sessions where Director has not yet cast own vote
3. Sessions waiting for other members
4. Closed sessions awaiting finalization

| Column           | Description                                                                                          |
| ---------------- | ---------------------------------------------------------------------------------------------------- |
| Reference Number | Monospace                                                                                            |
| Bank             | Originating bank                                                                                     |
| Amount           | Formatted amount + currency, right-aligned                                                           |
| Status           | Status badge (indigo for `EXECUTIVE_VOTING_OPEN`, dark-indigo for `EXECUTIVE_VOTING_CLOSED`)         |
| My Vote          | Same conventions as Executive Member's table                                                         |
| Voting Progress  | Tally + member-pending count + "Ready to Close" chip when all voted                                  |
| Tied?            | Tie indicator if tally is equal (informational; visible at session close)                            |
| Age in Stage     | Time since session opened                                                                            |
| Actions          | "تصويت" / "إغلاق الجلسة" / "إصدار نهائي" / "حسم التعادل" depending on state                          |

Rows in the "ready to close" state are visually emphasized (left border + slight background tint) because the Director's action there directly unblocks downstream stages.

---

**FX confirmation queue table:**

Shows requests in `FX_CONFIRMATION_PENDING` (and legacy `CUSTOMS_DECLARATION_ISSUED` not yet completed). Sorted by age in stage.

| Column           | Description                                                                                |
| ---------------- | ------------------------------------------------------------------------------------------ |
| Reference Number | Monospace                                                                                  |
| Bank             | Originating bank                                                                           |
| Amount           | Formatted amount + currency, right-aligned                                                 |
| Status           | Status badge                                                                               |
| FX Document State| "بانتظار التوقيع" (generated but not signed) / "تم التوقيع" (signed copy uploaded)         |
| Age in Stage     | Time since `SWIFT_UPLOADED` / `FX_CONFIRMATION_PENDING` transition                         |
| Actions          | "تحميل PDF" + "رفع الموقع" depending on state; both routed to detail page                  |

---

**States:** skeleton on load; reassuring empty state ("لا توجد إجراءات مديرية مطلوبة حالياً ✓") on empty; inline error with retry.

---

### Requests List (`/requests`)

Scope: all banks. The Director's requests list is the cross-bank executive/post-executive registry.

**Page header:**

- Title: "طلبات تمويل الواردات"
- Subtitle: "جميع الطلبات في المراحل التنفيذية وما بعد الاعتماد"
- Breadcrumbs: الرئيسية → طلبات التمويل

---

**Stage tabs (Director-aware):**

| Tab key             | Label                       | Mapped statuses                                                       |
| ------------------- | --------------------------- | --------------------------------------------------------------------- |
| `ready_to_close`    | جاهز للإغلاق                | `EXECUTIVE_VOTING_OPEN` where all members have voted                  |
| `ready_to_finalize` | جاهز للإصدار النهائي        | `EXECUTIVE_VOTING_CLOSED`                                             |
| `pending_my_vote`   | يحتاج صوتي                  | `EXECUTIVE_VOTING_OPEN` where I have not voted                        |
| `voting_open`       | التصويت مفتوح               | `EXECUTIVE_VOTING_OPEN` (all instances)                               |
| `fx_pending`        | تأكيد مصارفة معلق           | `FX_CONFIRMATION_PENDING`                                             |
| `swift_in_progress` | السويفت قيد التنفيذ         | `EXECUTIVE_APPROVED`, `WAITING_FOR_SWIFT`, `SWIFT_UPLOADED`           |
| `approved`          | معتمد                       | `EXECUTIVE_APPROVED`                                                  |
| `completed`         | مكتمل                       | `CUSTOMS_DECLARATION_ISSUED`, `COMPLETED`                             |
| `rejected`          | مرفوض                       | `EXECUTIVE_REJECTED`                                                  |
| `all`               | الكل                        | All executive-relevant statuses                                       |

The `ready_to_close`, `ready_to_finalize`, `pending_my_vote`, and `fx_pending` tabs are the Director's actionable surfaces and sit first.

---

**Toolbar:**

- Search input (reference, supplier, merchant, invoice number, bank)
- Bank filter dropdown
- Customize Columns dropdown
- Export button (CSV; filter-scoped)
- Saved Views dropdown ("High-value pending", "Voting > 48h", "FX pending > 24h")
- Refresh button

---

**Data table:** same structure as Executive Member's table, with two additional columns: "Ready to Close" chip indicator and "FX Document State" for FX-stage rows.

---

### Request Detail (`/requests/[id]`)

The Director's request detail is the most action-rich detail page in the platform. It combines:

- All Executive Member voting functionality (inline VotingPanel, vote casting)
- Director-exclusive voting controls (close session, finalize, tie-break override)
- FX confirmation completion controls (download → external sign → re-upload)
- Cross-bank read-only context

**Page header:**

- Title: Request reference (monospace)
- Breadcrumbs: الرئيسية → طلبات التمويل → [Reference]
- Status badge (full canonical label)
- Print button → `/requests/[id]/print`
- "تحميل ملف الحالة" (Case File) action — exports a comprehensive case-file PDF (request data + workflow timeline + voting record + documents list)

---

**Context banners:**

1. **ReadyToCloseBanner** (indigo) — `EXECUTIVE_VOTING_OPEN` with all members voted. Title: "جميع الأعضاء صوّتوا — الجلسة جاهزة للإغلاق." Subtitle: tally summary. CTA: "إغلاق الجلسة" (primary).
2. **TieBreakBanner** (amber) — `EXECUTIVE_VOTING_CLOSED` with tied tally. Title: "تعادل النتائج — يتطلب حسم المدير." Subtitle: tally + member count. CTA: "حسم التعادل" (primary).
3. **ReadyToFinalizeBanner** (indigo) — `EXECUTIVE_VOTING_CLOSED` with non-tied tally. Title: "الجلسة مغلقة — جاهزة للإصدار النهائي."
4. **FXReadyBanner** (cyan) — `FX_CONFIRMATION_PENDING`. Title: "جاهز لإصدار تأكيد المصارفة الخارجية." CTA: "تحميل النموذج المُنشأ" + "رفع النسخة الموقعة" depending on substate.
5. **VotingPendingBanner** (indigo) — `EXECUTIVE_VOTING_OPEN` where the Director has not yet cast their own vote. Same as Executive Member's pattern.
6. **LockedBanner** (gray; red for terminal) — for terminal/completed states.

Banner priority resolves to whichever is most actionable for the Director right now.

---

**WorkflowProgress:** standard horizontal stage progress bar.

---

**VotingPanel (inline, above tabs):**

Same composition as Executive Member's VotingPanel, with Director-exclusive controls added:

**Director additions:**

- **Vote buttons** (Approve / Reject) — present when voting is open and Director has not voted (same as members)
- **"إغلاق الجلسة" (Close Session)** — appears when status = `EXECUTIVE_VOTING_OPEN` AND all active executive members have cast their votes. Disabled with a specific tooltip when any member has not voted: "بانتظار صوت: [comma-separated member names]". Confirmation modal:
  - Title: "إغلاق جلسة التصويت"
  - Body: tally summary; reminder that closing transitions to `EXECUTIVE_VOTING_CLOSED` and unlocks the finalize step
  - Buttons: Cancel / Close Session (primary indigo)
- **"إصدار قرار نهائي" (Finalize Decision)** — appears when status = `EXECUTIVE_VOTING_CLOSED` AND tally is NOT tied. Resolves to `EXECUTIVE_APPROVED` or `EXECUTIVE_REJECTED` based on majority. Confirmation modal:
  - Title: "إصدار القرار النهائي: [اعتماد / رفض]"
  - Body: tally summary + the resulting status + downstream effect (approval auto-chains to `WAITING_FOR_SWIFT`)
  - Optional Director note textarea
  - Buttons: Cancel / Finalize (semantic color matching outcome)
- **"حسم التعادل" (Tie-break Resolution)** — appears when status = `EXECUTIVE_VOTING_CLOSED` AND tally is tied. The Director casts a deciding vote with mandatory reason. Modal:
  - Title: "حسم تعادل التصويت"
  - Body: tally summary explaining the tie
  - Two large buttons: "اعتماد" (green) / "رفض" (rose) — selecting one shows the reason textarea
  - Mandatory reason textarea (min 20 chars) explaining the override basis
  - Final confirmation button matching the selected outcome
- **Director override path** — even with a non-tied tally, the Director may have authority to override (subject to platform policy). When this authority is enabled, an "تجاوز نتيجة التصويت" (Override) button appears as a separate destructive action, requiring an explicit reason. This is governance-critical and is audit-logged distinctly from normal finalization.

After finalization:

- Approved → VotingPanel transitions to a read-only approved-state card; the auto-chain moves the request to `WAITING_FOR_SWIFT`; a "View SWIFT progress" link appears
- Rejected → red rejection summary card; the request becomes terminal
- Tie-break or override → both the original tally and the Director's deciding vote (with reason) are preserved in the panel and in the audit trail

---

**FX Confirmation controls (in the ActionsPanel, not in the VotingPanel):**

These controls appear when status ∈ {`FX_CONFIRMATION_PENDING`, `CUSTOMS_DECLARATION_ISSUED`}.

Three-step sequence:

1. **"تحميل ملف تأكيد المصارفة" (Download FX Confirmation PDF)** — downloads the system-generated PDF (rendered server-side via DomPDF with the request data, CBY letterhead, RTL Arabic typography, and signature/stamp placeholders). The download is audit-logged. The button transitions to "تم التحميل — جاهز للرفع" after first download.
2. **"رفع الملف الموقع/المختوم" (Upload Signed/Stamped PDF)** — file picker opens; PDF only, max 10 MB. SHA-256 checksum confirmed. The file is stored as the signed external FX confirmation document; visible to Director, Bank Reviewer, CBY Admin.
3. **"إتمام تأكيد المصارفة" (Complete FX Confirmation)** — enabled only after the signed PDF is uploaded. Confirmation modal:
   - Title: "إتمام دورة الطلب"
   - Body: "سيتم إغلاق الطلب نهائياً وتسجيله كمكتمل في السجل التنفيذي."
   - Buttons: Cancel / Complete (primary green)
   - On confirm: backend wraps the FX completion in a single transaction with row-level locking; status transitions to `COMPLETED` (or legacy `CUSTOMS_DECLARATION_ISSUED` for older requests); all relevant parties receive completion notifications

The three-step flow is intentionally sequential: the Director cannot skip the download (audit-logged), cannot complete without the signed upload, and cannot interrupt the completion transaction. Mid-step abandonment is safe — the document remains downloadable for re-attempt.

---

**Tabs:**

| Tab            | Purpose                                                                                |
| -------------- | -------------------------------------------------------------------------------------- |
| المعلومات      | Request and supplier/shipment fields, read-only                                        |
| الوثائق        | Full DocumentChecklist — every document including signed external FX confirmation       |
| الأطراف        | Full actor list across all stages                                                      |
| التصويت        | Detailed vote-by-member breakdown including Director's tie-break/override record       |
| تأكيد المصارفة | FX completion lifecycle visualization (generated → downloaded → signed → uploaded → completed) |
| السجل          | Full business-readable stage history with auto-transitions labeled                     |

The FX Confirmation tab is unique to this role. It shows the FX completion sub-lifecycle as a small horizontal mini-timeline so the Director can see exactly which step they are on for the current request.

---

**ActionsPanel (right column):**

The panel is **state-dense** for this role. Composition depends on current status:

- `EXECUTIVE_VOTING_OPEN`: voting controls (own vote if pending) + close-session button (with tooltip listing pending members if disabled)
- `EXECUTIVE_VOTING_CLOSED` (non-tied): finalize button (primary, semantic color matching expected outcome) + optional override button if policy enables
- `EXECUTIVE_VOTING_CLOSED` (tied): tie-break primary CTA + tally summary
- `WAITING_FOR_SWIFT` / `SWIFT_UPLOADED`: informational "بانتظار السويفت من موظف البنك" with link to SWIFT progress tracking; no Director action available here
- `FX_CONFIRMATION_PENDING`: three-step FX completion sequence (Download → Upload Signed → Complete)
- `COMPLETED` / terminal: empty panel; LockedBanner at top

---

### Print Request (`/requests/[id]/print`)

Same print-optimized A4 layout as DATA_ENTRY's, with full voting tally + Director's signature line + completion timestamp included for completed requests.

---

### External FX Confirmation (`/customs`)

The Director's dedicated queue for issuing and completing external FX confirmations. The route name `/customs` is retained as a legacy URL; the page labels use the updated "external FX confirmation" terminology throughout.

---

**Page header:**

- Title: "تأكيد المصارفة الخارجية"
- Subtitle: "إصدار وإتمام تأكيدات المصارفة الخارجية للطلبات المعتمدة من اللجنة التنفيذية"
- Breadcrumbs: الرئيسية → تأكيد المصارفة الخارجية

---

**Two-column grid layout:**

**Left card — Ready for Issuance (طلبات جاهزة للإصدار):**

- Header: "طلبات جاهزة للإصدار ([count])" with a green PackageCheck icon
- List of requests in `FX_CONFIRMATION_PENDING` (substate: PDF generated but not yet downloaded by Director, or downloaded but not yet signed/uploaded)
- Each entry: Reference Number, Bank, Merchant, Amount, Age in stage
- Per-entry actions:
  - "تحميل النموذج" — downloads the generated PDF; transitions the entry visually to "Awaiting signed upload"
  - "رفع الموقع" — opens upload modal once PDF has been downloaded
  - "عرض الطلب" — opens request detail
- Sort: oldest in stage first (SLA-aware)

**Right card — Completed (تم الإصدار):**

- Header: "تم الإصدار ([count])" with a Truck or CheckCircle icon
- List of requests in `CUSTOMS_DECLARATION_ISSUED` or `COMPLETED` over the last N days (configurable lookback)
- Each entry: Reference Number, Bank, completion timestamp
- Per-entry actions:
  - "تحميل التأكيد الموقع" — downloads the signed external FX confirmation document
  - "عرض الطلب" — opens request detail
  - "إعادة طباعة" — opens `/customs/[id]/print`

**Empty states:**

- Left card empty: "لا توجد تأكيدات بانتظار الإصدار حالياً ✓"
- Right card empty: "لم يصدر أي تأكيد خلال الفترة المختارة"

---

### Print External FX Confirmation (`/customs/[id]/print`)

A print-optimized A4 layout of the generated external FX confirmation PDF. Contains: CBY letterhead, request metadata (reference, merchant, bank, supplier, amount, currency, goods description, invoice details), CBY committee approval reference, signature section, official stamp placeholder, RTL Arabic typography, page numbers, generation timestamp. Auto-triggers the browser print dialog 300 ms after load. No sidebar or app chrome.

This is the same file content as the system-generated PDF, surfaced for browser-based print/re-print as an alternative to download.

---

### FX Confirmation Preview (`/requests/[id]/customs-preview`)

An inline iframe preview of the generated FX confirmation PDF within the request detail context. Linked from the Documents tab when the request reaches `FX_CONFIRMATION_PENDING`. Allows the Director to review the generated document inside the app before downloading or printing.

---

### Reports (`/reports`)

The Director sees the same cross-bank reports page as Executive Member, with additional Director-scoped sections:

**Director-specific sections:**

- **Voting lifecycle analytics:** sessions opened, closed, finalized; average voting duration; average time-to-close after all members voted; tie-break frequency; override frequency (governance metric)
- **FX completion analytics:** average time from `SWIFT_UPLOADED` to `COMPLETED`; FX completion backlog; per-bank breakdown of FX delays attributable to the Director vs. attributable to the bank
- **Decision outcome distribution:** approval vs rejection vs override per period

All Director-exclusive metrics are clearly labeled — these are governance self-awareness metrics, not bank-comparison tools.

---

### Audit (`/audit`)

The Director has access to the audit surface for governance investigation. The full audit page is described in detail in `cby-admin.md`; the Director's view is read-only and intended for compliance investigation, not for editing audit policies.

Director-specific audit interests:

- Voting events (vote cast, vote changed, voting closed, decision finalized, tie-break/override applied)
- Document events (FX confirmation generated, downloaded, signed-upload completed)
- Permission events (forbidden-action attempts on Director-exclusive endpoints)
- Admin changes affecting executive committee composition

The Director uses Audit primarily to verify governance integrity (e.g. "did anyone attempt to close a voting session bypassing the all-members-voted rule"), not to monitor day-to-day operational activity.

---

### Notifications (`/notifications`)

The Director receives notifications for:

- New voting session opened (auto-opened after support approval) — informational
- All members voted on a session (ready to close) — high priority
- Voting session closed and finalize-pending — high priority (when not closed by self)
- Session has tied tally — high priority (tie-break required)
- SWIFT uploaded — `FX_CONFIRMATION_PENDING` ready for me to act — highest priority
- FX confirmation completed by me — confirmation
- High-value request alert (configurable threshold)
- Sessions aging beyond configured Director SLA (informational escalation)
- Audit alert: forbidden-action attempt on Director-exclusive endpoint (governance signal)

Bank-side operational events, claim transfers, and per-bank intake events are not delivered to this role.

Page structure is identical to `data-entry.md`.

---

### Settings (`/settings`)

Same 6-tab layout as `data-entry.md`. Notification preferences default to all Director-relevant events enabled. MFA is required (not optional) and surfaces prominently.

---

### Profile (`/profile`)

Same 3-column layout as `data-entry.md`. Center stats highlight: sessions closed, decisions finalized, FX confirmations completed, average time-to-close, average time-to-FX-completion. The MFA status card is most prominent for this role.

---

## Forbidden Actions Reference

COMMITTEE_DIRECTOR cannot, under any UI condition:

- Manually open a voting session (sessions open automatically after support approval)
- Close a voting session before all active executive members have voted (UI must disable the close button with a specific tooltip listing pending members)
- Vote more than once per session
- Vote on behalf of another executive member
- Hold the `EXECUTIVE_MEMBER` role simultaneously (CBY Admin enforces during role assignment)
- Upload SWIFT documents (bank's SWIFT Officer territory)
- Skip the FX completion three-step sequence (download → sign → re-upload)
- Modify request business data (intake data is owned by Data Entry)
- Reverse a finalized executive decision or a completed request
- Act as a generic CBY super-admin (system administration belongs to `CBY_ADMIN`)
- Manage banks, entities, document rules, or system settings (CBY Admin)
- Manage CBY-side staff identity (CBY Admin)

The UI must not render any of these controls. The Director is a **workflow authority**, not an administrative override.

---

## Cross-Role Handoffs

COMMITTEE_DIRECTOR sits at multiple critical handoff points:

1. **EXECUTIVE_MEMBER → COMMITTEE_DIRECTOR (on full participation):** all members voted; session is ready to close. The Director closes and finalizes.
2. **COMMITTEE_DIRECTOR → SWIFT_OFFICER (on `EXECUTIVE_APPROVED`, auto):** finalization with approval auto-chains the request to `WAITING_FOR_SWIFT`. The bank's SWIFT Officer is notified and takes ownership.
3. **SWIFT_OFFICER → COMMITTEE_DIRECTOR (on `SWIFT_UPLOADED`, auto):** SWIFT submission auto-chains to `FX_CONFIRMATION_PENDING`. The Director receives a notification and the FX queue surfaces the request.
4. **COMMITTEE_DIRECTOR → COMPLETED (on signed re-upload):** the three-step FX completion transitions the request to `COMPLETED`. All parties (requester, bank reviewer, bank admin, CBY admin) receive completion notifications.
5. **COMMITTEE_DIRECTOR → terminal (on `EXECUTIVE_REJECTED`):** rejection is final; no resubmission path; the requester and bank reviewer are notified.

The Director is the **single point of completion** for every approved request. Operational continuity at this role is platform-critical — CBY Admin's staff page should warn if there is no active Director.

---

## UX Principles

- The Director is the final authority and the role's UI should feel that way: calm, governance-grade, deliberate, audit-aware. No clutter, no decorative analytics, no shortcuts.
- The "ready to close" and "FX confirmation pending" queues are the Director's two most actionable surfaces. Both must be visible from the dashboard within five seconds.
- The all-members-voted rule is non-negotiable. The close-session button must be **disabled** with a specific tooltip listing pending members — never silently blocking the user. Telling the Director "who is holding things up" enables them to follow up directly.
- Tie-break and override require mandatory reasons. The audit trail must distinguish member votes from Director tie-break/override votes.
- The FX completion three-step sequence is sequential and audited. Download must happen before sign-upload; sign-upload must happen before completion. The UI enforces order even if the backend would technically allow skip.
- The FX completion is wrapped in a single backend transaction. The UI's confirmation modal must reflect this irreversibility — the request transitions to `COMPLETED` on confirm, not on a subsequent save.
- Cross-bank visibility is for governance, not investigation. The Director sees enough cross-bank context to make informed decisions but does not become a CBY admin.
- The role's MFA is required, not optional. The Profile and Settings surfaces elevate MFA status visibly.
- The role's notification stream is curated to operational and governance-critical events only. No per-request operational noise.
- The `/customs` route alias is retained for legacy URL compatibility, but the page labels and all new UI copy use "تأكيد المصارفة الخارجية" / "External FX Confirmation" terminology consistently.
- The Director's dashboard composite action-required strip is the platform's most consequential surface — it represents the single Director's full workload across voting lifecycle, finalization, tie-break, and FX completion. Operational continuity depends on it being scannable.

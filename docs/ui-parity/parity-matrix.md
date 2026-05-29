# UI Parity Matrix

Each row is a parity-evidence triplet: spec citation → visual reference → implementation diff.

Story 9.2 produced the initial matrix. Story 12.1 appends rows for the four Tier 1 operational roles.

---

## Story 12.1 — Tier 1 Operational Roles UX Uplift

### DATA_ENTRY — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/data-entry.md#Dashboard` — action-required strip above KPIs (hidden when 0); 4 KPI cards (Completed green / Under CBY blue / Needs Correction amber / Drafts gray), all clickable; empty state hides KPI grid entirely |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/data-entry-dashboard.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/data-entry-dashboard.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/DataEntryDashboard.vue`: moved action-required strip above KPI grid; fixed labels (صدر التأكيد, مسودات); made KPIs clickable with `/requests?tab=<key>` routing; added empty-state KPI hide; `frontend/app/constants/workflow.ts`: reordered DATA_ENTRY ROLE_BUCKETS to spec order (returned→draft→submitted→processing→completed→rejected→all) |

---

### DATA_ENTRY — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/data-entry.md#Requests List` — tab order: returned, draft, submitted, processing, completed, rejected, all; all status labels via getBusinessStatus() simplified labels |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/data-entry-requests.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/data-entry-requests.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/constants/workflow.ts`: ROLE_BUCKETS[DATA_ENTRY] reordered; `DRAFT_REJECTED_INTERNAL` moved to `returned` bucket |

---

### BANK_REVIEWER — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-reviewer.md#Dashboard` — SUPPORT_REJECTED action strip at top; KPI order: Pending Review (amber) / Rejected by Support (rose) / At CBY (blue) / Approved-Completed (green); review-queue table with Created By column + segregation tooltip; downstream tracking table |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/bank-reviewer-dashboard.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/bank-reviewer-dashboard.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/BankReviewerDashboard.vue`: added SUPPORT_REJECTED action strip; reordered KPIs; added Created By column with segregation tooltip; added downstream tracking table |

---

### BANK_REVIEWER — Request Detail (SegregationBlockedBanner)

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-reviewer.md#Request Detail` — SegregationBlockedBanner (gray, info-blue accent) when current user is the original creator; decision buttons absent (not disabled) |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/bank-reviewer-detail-segregation.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/bank-reviewer-detail-segregation.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/components/banners/SegregationBlockedBanner.vue` (new); `frontend/app/pages/requests/[id]/index.vue`: mounted SegregationBlockedBanner when created_by === current user; ActionsPanel decision buttons hidden (v-if, not disabled) |

---

### BANK_REVIEWER — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-reviewer.md#Requests List` — tab order: pending, support_rejected, bank_returned, support_returned, at_cby, completed, rejected, all |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/bank-reviewer-requests.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/bank-reviewer-requests.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/constants/workflow.ts`: ROLE_BUCKETS[BANK_REVIEWER] rewritten to spec order |

---

### SUPPORT_COMMITTEE — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/support-committee.md#Dashboard` — active-claim strip below header (indigo, highest prominence); KPI order: Waiting for Claim (amber) / Active by Me (indigo) / Claimed by Others (gray) / Recently Approved (green); queue table with 3 claim-state row tints and claim-state-dependent action button |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/support-committee-dashboard.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/support-committee-dashboard.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/SupportCommitteeDashboard.vue`: added active-claim strip; reordered KPIs; implemented 3-state row tints with claim-state-dependent action buttons |

---

### SUPPORT_COMMITTEE — Request Detail (claim banners)

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/support-committee.md#Request Detail` — ActiveReviewBanner (indigo, heartbeat + TTL countdown) / ClaimedByOthersBanner (gray, no decisions) / UnclaimedBanner (amber, مطالبة بالطلب CTA); heartbeat every 60s while ActiveReviewBanner mounted |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/support-committee-detail.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/support-committee-detail.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/components/banners/ActiveReviewBanner.vue` (new with TTL countdown + heartbeat dot); `frontend/app/components/banners/ClaimedByOthersBanner.vue` (new); `frontend/app/components/banners/UnclaimedBanner.vue` (new); `frontend/app/pages/requests/[id]/index.vue`: banner selection logic |

---

### EXECUTIVE_MEMBER — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/executive-member.md#Dashboard` — pending-vote action strip (indigo, highest priority); 3-KPI grid (My Voting Queue indigo / Approval green / Rejection rose); voting-queue table with My Vote column (4 states) + Voting Progress column + indigo row tint for pending-my-vote rows |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/executive-dashboard.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/executive-dashboard.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/ExecutiveDashboard.vue`: added pending-vote action strip; reduced to 3 KPIs for EXECUTIVE_MEMBER; added My Vote + Voting Progress columns; enforced sort order |

---

### EXECUTIVE_MEMBER — Request Detail (VotingPendingBanner / VotedConfirmationBanner)

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/executive-member.md#Request Detail` — VotingPendingBanner (indigo, when open and not yet voted); VotedConfirmationBanner (gray, after submission); VotingPanel tally pills + per-member rows masked during open voting |
| **Baseline screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/baseline/executive-detail.png` absent) |
| **After screenshot** | Not captured in repository at review time (`docs/ui-parity/screenshots/12-1/after/executive-detail.png` absent) |
| **Implementation diff** | Story 12.1 — `frontend/app/components/banners/VotingPendingBanner.vue` (new); `frontend/app/components/banners/VotedConfirmationBanner.vue` (new); `frontend/app/pages/requests/[id]/index.vue`: banner selection; `frontend/app/components/voting/VotingPanel.vue`: vote masking during EXECUTIVE_VOTING_OPEN |

---

## Story 12.2 — Tier 2 Administrative Roles UX Uplift

### CBY_ADMIN — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/cby-admin.md#Dashboard` — "إشراف فقط" read-only chip; global toolbar (date-range + bank-filter + refresh + last-updated + Export PDF); 6-KPI strategic strip (Active Workflow / SLA Violations / Open Voting / FX Pending / Bank Risk Alerts / System Availability) with mini sparklines, period-delta, severity coloring, drilldown; Workflow Pressure Map (Stage / Active Count / Avg Age / SLA Risk / Trend); Executive Voting Oversight (per-session member list not numeric quorum, no action buttons); Bank Risk Intelligence sortable table; Compliance & Audit Signals cards; Critical Events feed; no generic Recent Requests table |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-2/baseline/cby-admin-dashboard.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-2/after/cby-admin-dashboard.png` (pending playwright-cli capture) |
| **Implementation diff** | Story 12.2 — `frontend/app/components/dashboard/CbyAdminDashboard.vue`: full governance rewrite; added oversight chip, global toolbar, 6-KPI strip with sparklines, Workflow Pressure Map, Executive Voting Oversight, Bank Risk Intelligence, Compliance Signal cards, Critical Events feed; removed generic Recent Requests table |

---

### CBY_ADMIN — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/cby-admin.md#Requests List` — Smart Summary Bar of operational exceptions (clickable); operational tabs (Active / Needs Attention / Executive Voting / FX Pending / Rejected / Completed / All Requests); Saved Views; dual status representation (business badge + muted internal enum); intelligence columns (SLA State / Voting State / FX State / Risk Flags); Actions View-only (View Request / Open Timeline / Open Audit View) |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-2/baseline/cby-admin-requests.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-2/after/cby-admin-requests.png` (pending playwright-cli capture) |
| **Implementation diff** | Story 12.2 — `frontend/app/constants/workflow.ts`: ROLE_BUCKETS[CBY_ADMIN] rewritten to 6 operational tabs (active/needs_attention/executive_voting/fx_pending/rejected/completed); `frontend/app/pages/requests/index.vue`: added `cbySmartSummary` computed (needs_attention + voting + fx_pending + stalled counts) rendered as clickable filter chips below header for CBY_ADMIN only; Actions column already View-only via generic dropdown (View + Print only for non-draft non-bank-reviewer flows); intelligence columns deferred — require backend SLA/voting/risk fields not in current `GET /api/requests` response (zero-backend-change constraint) |

---

### CBY_ADMIN — Admin Pages (entities, cby-staff, workflow-docs, roles)

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/cby-admin.md#Entities`, `#CBY Staff Management`, `#Document Rules`, `#Permissions Reference` — density/micro-copy/empty-state alignment; CBY staff role allowlist (CBY roles + SWIFT_OFFICER provisioning); no workflow action affordances on any admin surface |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-2/baseline/cby-admin-entities.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-2/after/cby-admin-cby-staff.png` (pending playwright-cli capture) |
| **Implementation diff** | Story 12.2 — `frontend/app/pages/admin/entities.vue`, `cby-staff.vue`, `workflow-docs.vue`, `roles.vue`: density/micro-copy/empty-state polish; no workflow affordances; cby-staff role allowlist enforced at modal level |

---

### BANK_ADMIN — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-admin.md#Dashboard` — "إدارة وعرض" read-only oversight chip; header toolbar (date-range + refresh + last-updated + Export Bank Summary PDF); 4-KPI grid (Total gray / In Process blue / Approved-Completed green / Rejected rose with left-border when % > threshold), all clickable; conditional Operational Health strip (hidden when no risks); 4-card quick actions (Reports = primary blue); Monthly Trend dual-line SVG; Recent Bank Requests table (max 8 rows, read-only) |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-2/baseline/bank-admin-dashboard.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-2/after/bank-admin-dashboard.png` (pending playwright-cli capture) |
| **Implementation diff** | Story 12.2 — `frontend/app/components/dashboard/BankAdminDashboard.vue`: added oversight chip, header toolbar, corrected KPI order and colors, added conditional Operational Health strip, fixed quick-actions Reports=primary-blue, enhanced Monthly Trend with dual lines and hover tooltips |

---

### BANK_ADMIN — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-admin.md#Requests List` — operational stage tabs (pending / at_cby / swift_fx / completed / rejected / all); read-only oversight chip; Saved Views; data table with Created By role chip + Current Owner role chip + Age in Stage + Last Activity; Actions View-only |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-2/baseline/bank-admin-requests.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-2/after/bank-admin-requests.png` (pending playwright-cli capture) |
| **Implementation diff** | Story 12.2 — `frontend/app/constants/workflow.ts`: ROLE_BUCKETS[BANK_ADMIN] updated to add swift_fx bucket and DRAFT_REJECTED_INTERNAL to pending |

---

### BANK_ADMIN — Request Detail

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-admin.md#Request Detail` — ActionsPanel empty of decision buttons; informational sentence "الطلب حالياً في مرحلة [stage] — المسؤول: [role]"; DRAFT requests by admin → "تعديل الطلب" + "حذف المسودة"; Documents tab external FX PDF locked row with tooltip; no SegregationBlockedBanner |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-2/baseline/bank-admin-detail.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-2/after/bank-admin-detail.png` (pending playwright-cli capture) |
| **Implementation diff** | Story 12.2 — `frontend/app/pages/requests/[id]/index.vue`: BANK_ADMIN ActionsPanel shows informational sentence only (no buttons), with DRAFT self-authored exception; FX PDF locked row with tooltip; SegregationBlockedBanner excluded for this role |

---

### BANK_ADMIN — Staff Page

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-admin.md#Staff` — Access Health summary row (Active / MFA enabled % / Suspended / BANK_REVIEWER coverage / Recent role changes / Recent permission denials) with clickable filter cards; filter toolbar (Search / Role / Status / MFA / Last-login range / Clear); data table per spec; StaffModal role allowlist DATA_ENTRY + BANK_REVIEWER only (SWIFT_OFFICER and CBY roles absent) |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-2/baseline/bank-admin-staff.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-2/after/bank-admin-staff.png` (pending playwright-cli capture) |
| **Implementation diff** | Story 12.2 — `frontend/app/pages/staff.vue`: added Access Health summary row with clickable filter cards; enhanced filter toolbar; `frontend/app/components/staff/StaffModal.vue`: role allowlist already enforced via BANK_ADMIN_MANAGED_ROLES constant |

---

## Story 12.3 — Tier 3 Lifecycle Finalization Roles UX Uplift

### COMMITTEE_DIRECTOR — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/committee-director.md#Dashboard` — composite action-required strip + governance KPI cards + voting lifecycle table + FX queue table |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-3/baseline/director-dashboard.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-3/after/director-dashboard.png` |
| **Implementation diff** | `frontend/app/components/dashboard/ExecutiveDashboard.vue` — Director-specific composite strip (`ready_to_close`, `tie_break`, `fx_pending`), 4 KPI governance cards, voting lifecycle table, and FX queue section |

### COMMITTEE_DIRECTOR — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/committee-director.md#Requests List` — Director tab model and governance queue-first layout |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-3/baseline/director-requests.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-3/after/director-requests.png` |
| **Implementation diff** | `frontend/app/pages/requests/index.vue`, `frontend/app/constants/workflow.ts` — Director-aware tabs (`ready_to_close`, `ready_to_finalize`, `tie_break`, `fx_pending`, `active_voting`, `finalized`, `all`) and summary wiring |

### COMMITTEE_DIRECTOR — Request Detail

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/committee-director.md#Request Detail` — Director lifecycle summary surfaces, role gating, and no SWIFT upload controls |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-3/baseline/director-request-detail.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-3/after/director-request-detail-fx.png` |
| **Implementation diff** | `frontend/app/pages/requests/[id]/index.vue`, `frontend/app/components/voting/VotingPanel.vue`, `frontend/app/components/ActionsPanel.vue` — Director banner/timeline logic and lifecycle action gating |

### SWIFT_OFFICER — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/swift-officer.md#Dashboard` — action-required strip, SWIFT KPI set, queue table with two-pill document status |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-3/baseline/swift-dashboard.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-3/after/swift-dashboard.png` |
| **Implementation diff** | `frontend/app/components/dashboard/SwiftOfficerDashboard.vue` — pending strip, KPI cards, two-pill document indicator, SWIFT queue actions |

### SWIFT_OFFICER — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/swift-officer.md#Requests List` — SWIFT-focused tabs with `pending_swift` first and queue-first navigation |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-3/baseline/swift-requests.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-3/after/swift-requests.png` |
| **Implementation diff** | `frontend/app/pages/requests/index.vue`, `frontend/app/constants/workflow.ts` — SWIFT-first tab order and role-bound lifecycle buckets |

### SWIFT_OFFICER — Upload/Detail Gating

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/swift-officer.md#Upload Page` and `#Request Detail` — locked-data panel, disabled submit reasoning, and post-upload locked informational state |
| **Baseline screenshot** | Not captured at story start (`docs/ui-parity/screenshots/12-3/baseline/swift-upload.png` absent) |
| **After screenshot** | `docs/ui-parity/screenshots/12-3/after/swift-upload-gate.png`, `docs/ui-parity/screenshots/12-3/after/swift-upload-denied.png` |
| **Implementation diff** | `frontend/app/pages/requests/[id]/swift.vue`, `frontend/app/components/workflow/SwiftUploadForm.vue`, `frontend/app/pages/requests/[id]/index.vue` — locked summary, 3-section upload gate messages, and role/status lifecycle messaging |

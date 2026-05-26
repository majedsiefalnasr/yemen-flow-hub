# UI Parity Matrix

Each row is a parity-evidence triplet: spec citation → visual reference → implementation diff.

Story 9.2 produced the initial matrix. Story 12.1 appends rows for the four Tier 1 operational roles.

---

## Story 12.1 — Tier 1 Operational Roles UX Uplift

### DATA_ENTRY — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/data-entry.md#Dashboard` — action-required strip above KPIs (hidden when 0); 4 KPI cards (Completed green / Under CBY blue / Needs Correction amber / Drafts gray), all clickable; empty state hides KPI grid entirely |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/data-entry-dashboard.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/data-entry-dashboard.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/DataEntryDashboard.vue`: moved action-required strip above KPI grid; fixed labels (صدر التأكيد, مسودات); made KPIs clickable with `/requests?tab=<key>` routing; added empty-state KPI hide; `frontend/app/constants/workflow.ts`: reordered DATA_ENTRY ROLE_BUCKETS to spec order (returned→draft→submitted→processing→completed→rejected→all) |

---

### DATA_ENTRY — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/data-entry.md#Requests List` — tab order: returned, draft, submitted, processing, completed, rejected, all; all status labels via getBusinessStatus() simplified labels |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/data-entry-requests.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/data-entry-requests.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/constants/workflow.ts`: ROLE_BUCKETS[DATA_ENTRY] reordered; `DRAFT_REJECTED_INTERNAL` moved to `returned` bucket |

---

### BANK_REVIEWER — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-reviewer.md#Dashboard` — SUPPORT_REJECTED action strip at top; KPI order: Pending Review (amber) / Rejected by Support (rose) / At CBY (blue) / Approved-Completed (green); review-queue table with Created By column + segregation tooltip; downstream tracking table |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/bank-reviewer-dashboard.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/bank-reviewer-dashboard.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/BankReviewerDashboard.vue`: added SUPPORT_REJECTED action strip; reordered KPIs; added Created By column with segregation tooltip; added downstream tracking table |

---

### BANK_REVIEWER — Request Detail (SegregationBlockedBanner)

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-reviewer.md#Request Detail` — SegregationBlockedBanner (gray, info-blue accent) when current user is the original creator; decision buttons absent (not disabled) |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/bank-reviewer-detail-segregation.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/bank-reviewer-detail-segregation.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/components/requests/SegregationBlockedBanner.vue` (new); `frontend/app/pages/requests/[id]/index.vue`: mounted SegregationBlockedBanner when created_by === current user; ActionsPanel decision buttons hidden (v-if, not disabled) |

---

### BANK_REVIEWER — Requests List

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/bank-reviewer.md#Requests List` — tab order: pending, support_rejected, bank_returned, support_returned, at_cby, completed, rejected, all |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/bank-reviewer-requests.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/bank-reviewer-requests.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/constants/workflow.ts`: ROLE_BUCKETS[BANK_REVIEWER] rewritten to spec order |

---

### SUPPORT_COMMITTEE — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/support-committee.md#Dashboard` — active-claim strip below header (indigo, highest prominence); KPI order: Waiting for Claim (amber) / Active by Me (indigo) / Claimed by Others (gray) / Recently Approved (green); queue table with 3 claim-state row tints and claim-state-dependent action button |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/support-committee-dashboard.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/support-committee-dashboard.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/SupportCommitteeDashboard.vue`: added active-claim strip; reordered KPIs; implemented 3-state row tints with claim-state-dependent action buttons |

---

### SUPPORT_COMMITTEE — Request Detail (claim banners)

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/support-committee.md#Request Detail` — ActiveReviewBanner (indigo, heartbeat + TTL countdown) / ClaimedByOthersBanner (gray, no decisions) / UnclaimedBanner (amber, مطالبة بالطلب CTA); heartbeat every 60s while ActiveReviewBanner mounted |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/support-committee-detail.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/support-committee-detail.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/components/requests/ActiveReviewBanner.vue` (new with TTL countdown + heartbeat dot); `frontend/app/components/requests/ClaimedByOthersBanner.vue` (new); `frontend/app/components/requests/UnclaimedBanner.vue` (new); `frontend/app/pages/requests/[id]/index.vue`: banner selection logic |

---

### EXECUTIVE_MEMBER — Dashboard

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/executive-member.md#Dashboard` — pending-vote action strip (indigo, highest priority); 3-KPI grid (My Voting Queue indigo / Approval green / Rejection rose); voting-queue table with My Vote column (4 states) + Voting Progress column + indigo row tint for pending-my-vote rows |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/executive-dashboard.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/executive-dashboard.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/components/dashboard/ExecutiveDashboard.vue`: added pending-vote action strip; reduced to 3 KPIs for EXECUTIVE_MEMBER; added My Vote + Voting Progress columns; enforced sort order |

---

### EXECUTIVE_MEMBER — Request Detail (VotingPendingBanner / VotedConfirmationBanner)

| Leg | Evidence |
|-----|----------|
| **Spec citation** | `docs/user-view/executive-member.md#Request Detail` — VotingPendingBanner (indigo, when open and not yet voted); VotedConfirmationBanner (gray, after submission); VotingPanel tally pills + per-member rows masked during open voting |
| **Baseline screenshot** | `docs/ui-parity/screenshots/12-1/baseline/executive-detail.png` |
| **After screenshot** | `docs/ui-parity/screenshots/12-1/after/executive-detail.png` |
| **Implementation diff** | Story 12.1 — `frontend/app/components/requests/VotingPendingBanner.vue` (new); `frontend/app/components/requests/VotedConfirmationBanner.vue` (new); `frontend/app/pages/requests/[id]/index.vue`: banner selection; `frontend/app/components/voting/VotingPanel.vue`: vote masking during EXECUTIVE_VOTING_OPEN |

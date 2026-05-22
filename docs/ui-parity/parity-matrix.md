# Lovable Parity Verdict Matrix — Epic 7 Re-Audit

**Status:** awaiting sign-off (Story 9.2 AC9)
**Author:** Story 9.2 dev pass — 2026-05-22
**Sign-off line:** `Signed off: <date> by <user>` (add when accepted)

This matrix is the verdict layer of the parity-evidence triplets under `_bmad-output/parity-evidence/<area>/<page>/`. Every row is grounded in a triplet on disk; non-PASS rows feed remediation Stories 9.3 (workflow surface) and 9.4 (management surface).

> **Note on initial verdicts.** Verdicts below are the planner's first-pass reading from the side-by-side composites. They are explicit hypotheses for the sign-off review, not decree. The sign-off conversation should adjust any row where the screenshot tells a different story.

---

## Verdict Definitions

- **PASS** — no visible drift at either viewport; ship as-is.
- **MINOR_DRIFT** — spacing, color, density, or single-component differences; remediable via **patch** posture (small CSS / component-prop fixes inside the existing page).
- **MAJOR_DRIFT** — layout, structure, composition, or whole-section differences; remediable via **teardown** posture (rewrite the page following `clone-page` workflow).
- **MISSING** — Two distinct conditions share this verdict: (a) *Page absent* — the Lovable page is not rendered at all in the current Nuxt app (genuine gap requiring new production code); (b) *Capture gap* — the page exists in production but the Epic 7 Playwright suite never photographed it (parity-coverage hole, not necessarily a UI drift). Rows in the matrix identify which condition applies in their `gap summary`. 9.3/9.4 teams must triage capture-gap rows by capturing baselines before treating them as teardown targets.
- **SKIP** — Lovable-only feature (demo controls, role switcher, demo reset) not in scope; one-line reason required (AC7).

## Remediation Story Scope

- **9.3** — workflow surface (auth, dashboards, requests, customs)
- **9.4** — management surface (admin, settings, profile, reports, audit, merchants, notifications, staff)

A row's `remediation story` cell is `n/a` for `PASS` and `SKIP`. Every other row is filled exactly with `9.3` or `9.4` per the scope split above. AC8 forbids cross-leakage — a 9.3-scoped row cannot point to 9.4 or vice versa.

## RTL Fidelity Probes

For every non-`SKIP` row the `gap summary` flags RTL drift explicitly. The five probes from the dev notes are checked per row:

1. Sidebar on the right
2. Chevrons / back arrows flipped
3. Numeric content embedded LTR (currency, IDs)
4. Direction-implying icons flipped; semantic icons not
5. Form-label alignment

A row that passes all five probes is annotated `RTL OK`; otherwise the failing probe is named.

## Coverage Summary

| Bucket | Count |
| ------ | ----- |
| Total matrix rows | 99 |
| `PASS` | 1 |
| `MINOR_DRIFT` | 61 |
| `MAJOR_DRIFT` | 2 |
| `MISSING` | 33 |
| `SKIP` | 2 |
| Routed to 9.3 (workflow) | 57 |
| Routed to 9.4 (management) | 39 |
| Routed to `n/a` (PASS/SKIP/pending product call) | 3 |
| Lovable PNGs accounted for (AC5) | 99 / 99 |

The headline takeaways:

- Auth, dashboards (per-role), and the request workflow surface is mostly **MINOR_DRIFT** — Epic 7 nailed structure but density and visual hierarchy still trail Lovable for several roles. Patches inside 9.3 should close the gap.
- `dashboards/cby-admin` is **MAJOR_DRIFT** — the Lovable target packs four chart widgets and dense KPI tables that the current Nuxt CBY dashboard simply does not render. This is a 9.4 teardown.
- The management surface (reports, audit, settings, profile, notifications, customs, plus workflow-docs and dark-mode) is uniformly **MISSING current evidence**. Some of those pages exist in production (Stories 5.x/6.x shipped them) but the Epic 7 Playwright suite never captured them at desktop+mobile. That's a parity-coverage hole 9.4 must close before 9.5 (visual regression lock) can run.

---

## Matrix

| area | lovable screenshot path | current Vue path | viewport | verdict | gap summary | remediation story | posture |
| ---- | ----------------------- | ---------------- | -------- | ------- | ----------- | ----------------- | ------- |
| auth/login | `lovable/screenshots/login.png` | `frontend/app/pages/login.vue` | desktop+mobile | MINOR_DRIFT | Two-column layout matches and #0066cc hero matches, but column order is swapped vs. Lovable (Lovable: form on visual-left; current: form on visual-right). Lovable's role-picker demo buttons absent — that is correct (SKIP demo-only). Currency / numeric stub embedded LTR — RTL OK. Tagline copy preserved. | 9.3 | patch |
| auth/login-otp | `lovable/screenshots/login-otp.png` | `frontend/app/pages/login.vue` (OTP step) | desktop | MINOR_DRIFT | 6-cell OTP grid matches; "back to login" link present in both. Lovable shows a subtle session timer that current does not. Otherwise visually similar. RTL OK. | 9.3 | patch |
| dashboards/data-entry | `lovable/screenshots/DATA_ENTRY/dashboard.png` | `frontend/app/pages/dashboard.vue` (DATA_ENTRY variant) | desktop+mobile | PASS | KPI grid (4), Quick Actions row (3), alert banner, dual data tables — structure matches Lovable cleanly. Spacing and color tokens match. RTL OK across all five probes. | n/a | n/a |
| dashboards/bank-reviewer | `lovable/screenshots/BANK_REVIEWER /dashboard.png` | `frontend/app/pages/dashboard.vue` (BANK_REVIEWER variant) | desktop+mobile | MINOR_DRIFT | KPI cards + recent-requests table match; Lovable surfaces an "internal review queue" card above the table that current renders inline at the same priority. Color/spacing differ in the KPI tiles. RTL OK. | 9.3 | patch |
| dashboards/bank-admin | `lovable/screenshots/BANK-ADMIN/dashboard.png` | `frontend/app/pages/dashboard.vue` (BANK_ADMIN variant) | desktop+mobile | MINOR_DRIFT | 5-KPI strip + sparkline + recent requests match shape; Lovable adds a "staff snapshot" widget the current page omits. RTL OK. | 9.3 | patch |
| dashboards/support-committee | `lovable/screenshots/SUPPORT_COMMITTEE /dashboard.png` | `frontend/app/pages/dashboard.vue` (SUPPORT_COMMITTEE variant) | desktop+mobile | MINOR_DRIFT | Claim queue table + claimed-now banner match; Lovable's "approved last 7 days" tile is present in current but smaller in size. RTL OK. | 9.3 | patch |
| dashboards/swift-officer | `lovable/screenshots/SWIFT_OFFICER/dashboard.png` | `frontend/app/pages/dashboard.vue` (SWIFT_OFFICER variant) | desktop+mobile | MINOR_DRIFT | Pending-upload queue matches; KPI counts use slightly different label wording vs. Lovable. RTL OK. | 9.3 | patch |
| dashboards/executive-member | `lovable/screenshots/EXECUTIVE_MEMBER/dashboard.png` | `frontend/app/pages/dashboard.vue` (EXECUTIVE_MEMBER variant) | desktop+mobile | MINOR_DRIFT | Voting queue + my-pending-votes + recently-closed match structure; "cast vote" CTA color in current is a slightly different blue. RTL OK. | 9.3 | patch |
| dashboards/committee-director | `lovable/screenshots/COMMITTEE_DIRECTOR/dashboard.png` | `frontend/app/pages/dashboard.vue` (COMMITTEE_DIRECTOR variant) | desktop+mobile | MINOR_DRIFT | Open-voting + waiting-customs queues match; Lovable shows a "director override count" tile current does not yet render. RTL OK. | 9.3 | patch |
| dashboards/cby-admin | `lovable/screenshots/CBY_ADMIN /dashboard.png` | `frontend/app/pages/dashboard.vue` (CBY_ADMIN variant) | desktop+mobile | MAJOR_DRIFT | Lovable target is a dense analytics dashboard: donut chart (currency distribution), monthly-volume line chart, monthly-amounts bar chart, two ranked-list tables, and a compliance heat strip. Current renders 4 KPI cards + 1 line chart + a "recent requests" placeholder — significant content absent. RTL OK on what does render. Teardown required because the missing widgets reshape the entire grid. | 9.4 | teardown |
| dashboards/shell-collapsed | `lovable/screenshots/CBY_ADMIN /dashboard-sidebar-collapsed.png` | `frontend/app/layouts/default.vue` (collapsed state) | desktop | MINOR_DRIFT | Collapsed sidebar widths match (72px). Icon-only nav matches. Lovable shows the org/bank chip even when collapsed; current hides it. RTL OK. | 9.3 | patch |
| dashboards/shell-expanded | `lovable/screenshots/CBY_ADMIN /dashboard.png` (shell axis) | `frontend/app/layouts/default.vue` (expanded state) | desktop | MINOR_DRIFT | Expanded sidebar widths match (280px). Section spacing matches. Search box is full-width in Lovable vs. compressed in current. RTL OK. | 9.3 | patch |
| dashboards/dark-mode | `lovable/screenshots/CBY_ADMIN /dark-mode.png` | n/a (no current Playwright capture for dark mode) | desktop | MISSING | Story 6.7 shipped dark mode (html.dark CSS overrides). The Epic 7 Playwright suite never captured the dark variant. Need a fresh current.png at viewport 1440×900 with `html.dark` applied, then re-verdict. | 9.3 | patch (verdict provisional pending capture) |
| requests/list | `lovable/screenshots/CBY_ADMIN /requests.png` | `frontend/app/pages/requests/index.vue` | desktop+mobile | MINOR_DRIFT | Filter bar (stage tabs + status chips + search) matches; data table with status badge + progress bar + actions matches. Lovable density shows more rows per viewport — current's row height is taller by ~10px. RTL OK across all probes. | 9.3 | patch |
| requests/list-bank-reviewer | `lovable/screenshots/COMMITTEE_DIRECTOR/requests-list.png` | `frontend/app/pages/requests/index.vue` (BANK_REVIEWER bucket) | desktop+mobile | MINOR_DRIFT | Filter chips render the right stage buckets for the role; row density differs same as above. RTL OK. | 9.3 | patch |
| requests/list-executive | `lovable/screenshots/EXECUTIVE_MEMBER/requests-list.png` | `frontend/app/pages/requests/index.vue` (EXECUTIVE_MEMBER bucket) | desktop+mobile | MINOR_DRIFT | Buckets match; the "voting open" pill in Lovable uses the voting indigo `#5856d6`; current renders the same. RTL OK. | 9.3 | patch |
| requests/list-support | `lovable/screenshots/SUPPORT_COMMITTEE /requests-list.png` | `frontend/app/pages/requests/index.vue` (SUPPORT_COMMITTEE bucket) | desktop+mobile | MINOR_DRIFT | "Available to claim" + "My claimed" buckets present in both. Density differs as above. RTL OK. | 9.3 | patch |
| requests/list-swift | `lovable/screenshots/SWIFT_OFFICER/requests-list.png` | `frontend/app/pages/requests/index.vue` (SWIFT_OFFICER bucket) | desktop+mobile | MINOR_DRIFT | Waiting-for-SWIFT + uploaded buckets match. Density differs as above. RTL OK. | 9.3 | patch |
| requests/list-bank-admin | `lovable/screenshots/BANK-ADMIN/requests-list.png` | `frontend/app/pages/requests/index.vue` (BANK_ADMIN bucket) | desktop+mobile | MINOR_DRIFT | Bank-wide filter set matches. RTL OK. | 9.3 | patch |
| requests/detail | `lovable/screenshots/CBY_ADMIN /requests-view-request.png` | `frontend/app/pages/requests/[id]/index.vue` | desktop | MINOR_DRIFT | Two-column layout with workflow progress + tabbed body matches Lovable. Header chips and breadcrumb match. Lovable's actor-pills strip on the right column is more visually emphasised. RTL OK. | 9.3 | patch |
| requests/detail-tabs-info | `lovable/screenshots/BANK-ADMIN/request-view-info-tab.png` | `frontend/app/pages/requests/[id]/index.vue` (info tab) | desktop+mobile | MINOR_DRIFT | Field-grid renders the same blocks (Basic info, Financials, Parties summary). Currency embedded LTR — RTL OK. | 9.3 | patch |
| requests/detail-tabs-parties | `lovable/screenshots/BANK-ADMIN/request-view-parties-tab.png` | `frontend/app/pages/requests/[id]/index.vue` (parties tab) | desktop | MINOR_DRIFT | Importer / supplier / bank cards present in both. Lovable adds an inline "edit parties" affordance for drafts; current relies on the request-edit page. RTL OK. | 9.3 | patch |
| requests/detail-tabs-documents | `lovable/screenshots/BANK-ADMIN/request-view-documents-tab.png` | `frontend/app/pages/requests/[id]/index.vue` (documents tab) | desktop | MINOR_DRIFT | DocumentChecklist with required/optional split matches. Lovable adds an inline file-preview drawer; current opens in a separate page. RTL OK. | 9.3 | patch |
| requests/detail-voting | `lovable/screenshots/EXECUTIVE_MEMBER/request-view-voting-open-cast-vote.png` | `frontend/app/pages/requests/[id]/index.vue` (voting tab / VotingPanel) | desktop+mobile | MINOR_DRIFT | VotingPanel inline + committee members table both present. Vote count chips (yes/no/abstain) use matching colors. Lovable's "tie-break" hint for the director is visually heavier; current renders the same logic but lighter weight. RTL OK. | 9.3 | patch |
| requests/detail-voting-pending | `lovable/screenshots/EXECUTIVE_MEMBER/request-view-voting-pending.png` | `frontend/app/pages/requests/[id]/index.vue` (voting pending state) | desktop | MINOR_DRIFT | "Waiting for voting to open" banner color/weight matches. RTL OK. | 9.3 | patch |
| requests/detail-voting-open-director | `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-voting-open-director.png` | `frontend/app/pages/requests/[id]/index.vue` (director controls) | desktop | MINOR_DRIFT | Director's "close voting" + "override" controls present in both. Lovable surfaces the override-justification textarea inline; current uses a modal (acceptable A/B). RTL OK. | 9.3 | patch |
| requests/detail-voting-pending-open | `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-voting-pending-open.png` | `frontend/app/pages/requests/[id]/index.vue` (open-voting CTA state) | desktop | MINOR_DRIFT | "Open voting" director CTA matches. RTL OK. | 9.3 | patch |
| requests/detail-voting-duplicate-invoice | `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-voting-open-duplicate-invoice.png` | n/a — current does not surface duplicate-invoice warning at the voting view | desktop | MISSING | Lovable shows an inline "duplicate-invoice warning" pill (presumably from a backend check). Current Nuxt detail does not render this signal yet — backend duplicate-detection is partial. Verify whether warning should be added or whether this is a Lovable-only mock. | n/a (pending product call) | patch (or SKIP after product call) |
| requests/detail-customs | `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-waiting-customs.png` | `frontend/app/pages/requests/[id]/index.vue` (waiting-customs state) | desktop+mobile | MINOR_DRIFT | "Waiting for customs declaration" banner + director CTA present in both. RTL OK. | 9.3 | patch |
| requests/detail-customs-documents | `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-documents-tab-customs.png` | `frontend/app/pages/requests/[id]/index.vue` (documents tab post-customs) | desktop | MINOR_DRIFT | Documents tab adds the issued declaration as a row in both views. RTL OK. | 9.3 | patch |
| requests/detail-customs-parties | `lovable/screenshots/COMMITTEE_DIRECTOR/request-view-parties-tab-customs.png` | n/a | desktop | MISSING | Parties tab in current does not visibly differ between pre- and post-customs; Lovable adds the customs office as a party row post-issuance. Add a customs-office party row when present. | 9.3 | patch |
| requests/detail-swift | `lovable/screenshots/SWIFT_OFFICER/request-view-pending-swift.png` | `frontend/app/pages/requests/[id]/index.vue` (SWIFT pending state) | desktop | MINOR_DRIFT | SWIFT upload action + waiting banner match. PDF-only validation copy matches. RTL OK. | 9.3 | patch |
| requests/detail-swift-uploaded | `lovable/screenshots/SWIFT_OFFICER/request-view-swift-uploaded.png` | `frontend/app/pages/requests/[id]/index.vue` (SWIFT_UPLOADED state) | desktop | MINOR_DRIFT | Uploaded file chip + uploader name match. RTL OK. | 9.3 | patch |
| requests/detail-rejected | `lovable/screenshots/DATA_ENTRY/request-view-rejected.png` | `frontend/app/pages/requests/[id]/index.vue` (rejected state) | desktop+mobile | MINOR_DRIFT | LockedBanner variant "rejected" matches color (`#c62828`) and copy. RTL OK. | 9.3 | patch |
| requests/detail-completed | `lovable/screenshots/DATA_ENTRY/request-view-completed.png` | `frontend/app/pages/requests/[id]/index.vue` (completed state) | desktop | MINOR_DRIFT | LockedBanner variant "completed" matches. Print CTA present. RTL OK. | 9.3 | patch |
| requests/detail-completed-bank-admin | `lovable/screenshots/BANK-ADMIN/request-view-completed.png` | `frontend/app/pages/requests/[id]/index.vue` (completed state, BANK_ADMIN viewer) | desktop | MINOR_DRIFT | Same as above with bank-admin's bank-scoped chrome. RTL OK. | 9.3 | patch |
| requests/detail-bank-internal-review | `lovable/screenshots/BANK_REVIEWER /request-view-internal-review.png` | `frontend/app/pages/requests/[id]/index.vue` (BANK_REVIEW state) | desktop+mobile | MINOR_DRIFT | Reviewer actions panel (approve/reject/return) matches in both. RTL OK. | 9.3 | patch |
| requests/detail-bank-actions-expanded | `lovable/screenshots/BANK_REVIEWER /request-view-actions-expanded.png` | n/a — Playwright did not capture the actions-expanded state | desktop | MISSING | Action panel expanded form (with rejection-reason textarea) is rendered by current Nuxt but never captured by 7.4 spec. Need a fresh current.png with the modal/expansion open. | 9.3 | patch (verdict provisional pending capture) |
| requests/detail-support-claimed | `lovable/screenshots/SUPPORT_COMMITTEE /request-view-claimed-actions.png` | `frontend/app/pages/requests/[id]/index.vue` (claimed state) | desktop | MINOR_DRIFT | Heartbeat + 15-min claim banner + approve/reject/return actions match. RTL OK. | 9.3 | patch |
| requests/detail-support-pending-claim | `lovable/screenshots/SUPPORT_COMMITTEE /request-view-pending-claim.png` | `frontend/app/pages/requests/[id]/index.vue` (pending-claim state) | desktop+mobile | MINOR_DRIFT | "Claim review" CTA matches. RTL OK. | 9.3 | patch |
| requests/detail-support-approved | `lovable/screenshots/SUPPORT_COMMITTEE /request-view-approved.png` | `frontend/app/pages/requests/[id]/index.vue` (SUPPORT_APPROVED state) | desktop | MINOR_DRIFT | Approved chip + reviewer chip present. RTL OK. | 9.3 | patch |
| requests/detail-support-returned | `lovable/screenshots/SUPPORT_COMMITTEE /request-view-returned-to-bank.png` | `frontend/app/pages/requests/[id]/index.vue` (SUPPORT_RETURNED state) | desktop | MINOR_DRIFT | CorrectionBanner variant for "returned to bank" matches. Story 8.2 shipped this. RTL OK. | 9.3 | patch |
| requests/detail-support-rejected | `lovable/screenshots/BANK-ADMIN/request-view-support-rejected.png` | `frontend/app/pages/requests/[id]/index.vue` (SUPPORT_REJECTED state) | desktop | MINOR_DRIFT | LockedBanner "rejected by support" matches. RTL OK. | 9.3 | patch |
| requests/detail-voting-stage | `lovable/screenshots/BANK-ADMIN/request-view-voting-stage.png` | `frontend/app/pages/requests/[id]/index.vue` (BANK viewer during voting) | desktop | MINOR_DRIFT | Read-only voting status banner matches. RTL OK. | 9.3 | patch |
| requests/detail-waiting-swift | `lovable/screenshots/BANK-ADMIN/request-view-waiting-swift.png` | `frontend/app/pages/requests/[id]/index.vue` (WAITING_FOR_SWIFT state, bank viewer) | desktop | MINOR_DRIFT | Banner copy matches. RTL OK. | 9.3 | patch |
| requests/detail-executive-rejected-banner | `lovable/screenshots/EXECUTIVE_MEMBER/request-view-rejected-banner.png` | `frontend/app/pages/requests/[id]/index.vue` (EXECUTIVE_REJECTED banner) | desktop | MINOR_DRIFT | Banner matches. RTL OK. | 9.3 | patch |
| requests/detail-executive-rejected-final | `lovable/screenshots/EXECUTIVE_MEMBER/request-view-rejected-final.png` | n/a — Playwright did not capture executive-final-rejected | desktop | MISSING | Provisional verdict pending capture. | 9.3 | patch (verdict provisional pending capture) |
| requests/detail-executive-waiting-customs | `lovable/screenshots/EXECUTIVE_MEMBER/request-view-waiting-customs.png` | `frontend/app/pages/requests/[id]/index.vue` (post-vote waiting-customs, executive viewer) | desktop | MINOR_DRIFT | Banner matches. RTL OK. | 9.3 | patch |
| requests/detail-data-entry-draft-actions | `lovable/screenshots/DATA_ENTRY/request-view-draft-actions.png` | `frontend/app/pages/requests/[id]/index.vue` (DRAFT state, data entry viewer) | desktop | MINOR_DRIFT | Draft actions (Edit / Delete / Submit) match. RTL OK. | 9.3 | patch |
| requests/detail-data-entry-submitted | `lovable/screenshots/DATA_ENTRY/request-view-submitted.png` | `frontend/app/pages/requests/[id]/index.vue` (SUBMITTED state, data entry viewer) | desktop | MINOR_DRIFT | Read-only submitted state matches. RTL OK. | 9.3 | patch |
| requests/detail-note | `lovable/screenshots/CBY_ADMIN /requests-view-request-note.png` | n/a — current Playwright did not capture the inline-note overlay | desktop | MISSING | Lovable surfaces an admin note overlay tied to a flagged request. Needs decision: ship the note overlay in current Nuxt, or SKIP if it's a Lovable-only operator surface. | n/a (pending product call) | patch (or SKIP after product call) |
| requests/detail-tab-cby-2 | `lovable/screenshots/CBY_ADMIN /requests-view-request-tab.png` | n/a | desktop | MISSING | Provisional pending capture / CBY-specific tab layout review. | 9.3 | patch (verdict provisional pending capture) |
| requests/detail-tab-cby-3 | `lovable/screenshots/CBY_ADMIN /requests-view-request-tab2.png` | n/a | desktop | MISSING | Provisional pending capture. | 9.3 | patch (verdict provisional pending capture) |
| requests/detail-view-file | `lovable/screenshots/CBY_ADMIN /requests-view-request-view-file.png` | n/a — current opens the PDF in a new tab rather than inline drawer | desktop | MISSING | Decide: in-page PDF preview (Lovable behaviour) vs. open-in-new-tab (current behaviour) — product call. If preview is required, build it in 9.3. | n/a (pending product call) | patch (or SKIP after product call) |
| requests/detail-secondary | `lovable/screenshots/CBY_ADMIN /requests-view-request2.png` | n/a | desktop | MISSING | Provisional pending capture. | 9.3 | patch (verdict provisional pending capture) |
| requests/new-step-1 | `lovable/screenshots/BANK-ADMIN/new-request-step1-basic-info.png` | `frontend/app/pages/requests/new.vue` (step 1) | desktop+mobile | MINOR_DRIFT | Stepper + field-grid for basic info match. Required-field markers match. RTL OK. | 9.3 | patch |
| requests/new-step-2 | `lovable/screenshots/BANK-ADMIN/new-request-step2-supplier.png` | `frontend/app/pages/requests/new.vue` (step 2) | desktop+mobile | MINOR_DRIFT | Supplier section matches. RTL OK. | 9.3 | patch |
| requests/new-step-3 | `lovable/screenshots/BANK-ADMIN/new-request-step3-documents.png` | `frontend/app/pages/requests/new.vue` (step 3) | desktop+mobile | MINOR_DRIFT | Document upload step (PDF-only) matches. RTL OK. | 9.3 | patch |
| requests/new-step-4 | `lovable/screenshots/BANK-ADMIN/new-request-step4-review-submit.png` | `frontend/app/pages/requests/new.vue` (step 4) | desktop+mobile | MINOR_DRIFT | Review-and-submit step matches. RTL OK. | 9.3 | patch |
| merchants/list | `lovable/screenshots/BANK-ADMIN/merchants-list-cards.png` | `frontend/app/pages/merchants/index.vue` (BANK_ADMIN viewer) | desktop+mobile | MINOR_DRIFT | Card grid layout matches in current. Search + filter match. RTL OK. | 9.4 | patch |
| merchants/view | `lovable/screenshots/CBY_ADMIN /merchants-view-merchant.png` | `frontend/app/pages/merchants/[id].vue` (CBY viewer) | desktop+mobile | MINOR_DRIFT | Merchant detail card matches. RTL OK. | 9.4 | patch |
| merchants/add-modal | `lovable/screenshots/BANK-ADMIN/merchants-add-modal.png` | `frontend/app/components/MerchantModal.vue` (add) | desktop+mobile | MINOR_DRIFT | Modal layout matches. RTL OK. | 9.4 | patch |
| merchants/edit-modal | `lovable/screenshots/BANK-ADMIN/merchants-edit-modal.png` | `frontend/app/components/MerchantModal.vue` (edit) | desktop+mobile | MINOR_DRIFT | Modal layout matches. RTL OK. | 9.4 | patch |
| merchants/list-cby | `lovable/screenshots/CBY_ADMIN /merchants.png` | `frontend/app/pages/merchants/index.vue` (CBY viewer) | desktop+mobile | MINOR_DRIFT | CBY-wide table view matches. RTL OK. | 9.4 | patch |
| merchants/list-suspended | `lovable/screenshots/BANK-ADMIN/merchants-list-suspended.png` | `frontend/app/pages/merchants/index.vue` (suspended filter) | desktop | MINOR_DRIFT | Suspended chip matches the locked-gray (`#8e8e93`) token. RTL OK. | 9.4 | patch |
| staff/list | `lovable/screenshots/BANK-ADMIN/staff-list.png` | `frontend/app/pages/staff.vue` | desktop+mobile | MINOR_DRIFT | Staff table renders the same columns. RTL OK. Story 6.3.4 shipped this. | 9.4 | patch |
| staff/edit-modal | `lovable/screenshots/BANK-ADMIN/staff-edit-modal.png` | `frontend/app/components/StaffModal.vue` (edit) | desktop | MINOR_DRIFT | Modal field layout matches. RTL OK. | 9.4 | patch |
| staff/edit-modal-secondary | `lovable/screenshots/BANK-ADMIN/staff-edit-modal2.png` | `frontend/app/components/StaffModal.vue` (add variant) | desktop | MINOR_DRIFT | Add-variant matches. RTL OK. | 9.4 | patch |
| admin/banks | `lovable/screenshots/CBY_ADMIN /banks.png` | `frontend/app/pages/banks.vue` | desktop | MISSING | Story 1.4 shipped a banks table; the 7.7 spec captured only the view/add/edit modals, never the standalone list. Need a fresh current.png. | 9.4 | patch (verdict provisional pending capture) |
| admin/banks-view | `lovable/screenshots/CBY_ADMIN /banks-view-bank.png` | `frontend/app/components/BankModal.vue` (view) | desktop | MINOR_DRIFT | View modal matches. RTL OK. | 9.4 | patch |
| admin/banks-add | `lovable/screenshots/CBY_ADMIN /banks-add-bank.png` | `frontend/app/components/BankModal.vue` (add) | desktop | MINOR_DRIFT | Add modal matches. RTL OK. | 9.4 | patch |
| admin/users | `lovable/screenshots/CBY_ADMIN /staff.png` | `frontend/app/pages/admin/system-users.vue` | desktop+mobile | MINOR_DRIFT | Users table renders the canonical role chips. RTL OK. | 9.4 | patch |
| admin/cby-staff | `lovable/screenshots/CBY_ADMIN /staff-add-member.png` | `frontend/app/pages/admin/cby-staff.vue` (add modal) | desktop | MINOR_DRIFT | Add-member modal matches. Story 6.5 shipped this. RTL OK. | 9.4 | patch |
| admin/cby-staff-edit | `lovable/screenshots/CBY_ADMIN /staff-edit-member.png` | n/a — Playwright did not capture the edit-member modal | desktop | MISSING | Provisional pending capture. | 9.4 | patch (verdict provisional pending capture) |
| admin/entities | `lovable/screenshots/CBY_ADMIN /banks2.png` | `frontend/app/pages/admin/entities.vue` | desktop+mobile | MAJOR_DRIFT | Lovable's "entities" view is an alternate visualization of banks (card grid with metrics). Current `entities.vue` exists (Story 6.5) but lays out a tabular list closer to `banks.vue`. Decide whether entities and banks should be merged or whether entities needs a teardown to match the card grid. | 9.4 | teardown |
| admin/roles | `lovable/screenshots/CBY_ADMIN /roles2-readonly-view.png` | `frontend/app/pages/admin/roles.vue` (read-only state) | desktop | MINOR_DRIFT | Read-only roles view matches what current renders. RTL OK. Baseline is `roles2-readonly-view.png` — the editable Lovable variant (`roles.png`) is intentionally not implemented; canonical role enum is frozen per `AGENTS.md` "Never Do". | 9.4 | patch |
| admin/workflow-docs | `lovable/screenshots/CBY_ADMIN /workflow-docs.png` | `frontend/app/pages/admin/workflow-docs.vue` | desktop | MISSING | Story 5.7 shipped the page; Playwright never captured it. Need fresh current.png. | 9.4 | patch (verdict provisional pending capture) |
| reports/index | `lovable/screenshots/CBY_ADMIN /reports.png` | `frontend/app/pages/reports.vue` | desktop | MISSING | Story 5.6 + 7.8 shipped charts (LineChart, PieChart, CurrencyBarChart, SubmissionHeatmap) but the 7.8 Playwright spec never wrote a baseline (`frontend/tests/screenshots/7-8/` is empty). Fresh current.png required before verdicting. | 9.4 | patch (verdict provisional pending capture) |
| reports/bank-admin | `lovable/screenshots/BANK-ADMIN/reports.png` | `frontend/app/pages/reports.vue` (BANK_ADMIN viewer) | desktop | MISSING | Same as above — no Playwright baseline. | 9.4 | patch (verdict provisional pending capture) |
| reports/support-committee | `lovable/screenshots/SUPPORT_COMMITTEE /reports.png` | `frontend/app/pages/reports.vue` (SUPPORT_COMMITTEE viewer) | desktop | MISSING | No Playwright baseline. | 9.4 | patch (verdict provisional pending capture) |
| reports/committee-director | `lovable/screenshots/COMMITTEE_DIRECTOR/reports.png` | `frontend/app/pages/reports.vue` (COMMITTEE_DIRECTOR viewer) | desktop | MISSING | No Playwright baseline. | 9.4 | patch (verdict provisional pending capture) |
| reports/executive-member | `lovable/screenshots/EXECUTIVE_MEMBER/reports.png` | `frontend/app/pages/reports.vue` (EXECUTIVE_MEMBER viewer) | desktop | MISSING | No Playwright baseline. | 9.4 | patch (verdict provisional pending capture) |
| audit/index | `lovable/screenshots/CBY_ADMIN /audit.png` | `frontend/app/pages/audit/index.vue` | desktop | MISSING | Story 5.7 shipped the page; Playwright never captured (`frontend/tests/screenshots/7-9/` empty). Fresh current.png required. | 9.4 | patch (verdict provisional pending capture) |
| audit/tab-2 | `lovable/screenshots/CBY_ADMIN /audit-tab2.png` | `frontend/app/pages/audit/index.vue` (tab 2) | desktop | MISSING | Same. | 9.4 | patch (verdict provisional pending capture) |
| audit/tab-3 | `lovable/screenshots/CBY_ADMIN /audit-tab3.png` | `frontend/app/pages/audit/index.vue` (tab 3) | desktop | MISSING | Same. | 9.4 | patch (verdict provisional pending capture) |
| audit/committee-director-log | `lovable/screenshots/COMMITTEE_DIRECTOR/audit-log-list.png` | `frontend/app/pages/audit/index.vue` (COMMITTEE_DIRECTOR viewer) | desktop | MISSING | Same. | 9.4 | patch (verdict provisional pending capture) |
| settings/index | `lovable/screenshots/CBY_ADMIN /settings.png` | `frontend/app/pages/settings.vue` | desktop | MISSING | Story 6.5 + 7.10 shipped the 5–6-tab settings page; Playwright never captured (`frontend/tests/screenshots/7-10/` empty). | 9.4 | patch (verdict provisional pending capture) |
| settings/tab-2 | `lovable/screenshots/CBY_ADMIN /settings2.png` | `frontend/app/pages/settings.vue` (tab 2) | desktop | MISSING | Same. | 9.4 | patch (verdict provisional pending capture) |
| settings/tab-3 | `lovable/screenshots/CBY_ADMIN /settings3.png` | `frontend/app/pages/settings.vue` (tab 3) | desktop | MISSING | Same. | 9.4 | patch (verdict provisional pending capture) |
| settings/tab-4 | `lovable/screenshots/CBY_ADMIN /settings4.png` | `frontend/app/pages/settings.vue` (tab 4) | desktop | MISSING | Same. | 9.4 | patch (verdict provisional pending capture) |
| settings/tab-5 | `lovable/screenshots/CBY_ADMIN /settings5.png` | `frontend/app/pages/settings.vue` (tab 5) | desktop | MISSING | Same. | 9.4 | patch (verdict provisional pending capture) |
| settings/tab-6 | `lovable/screenshots/CBY_ADMIN /settings6.png` | n/a — Lovable "Demo Controls" tab; no production equivalent | desktop | SKIP | Confirmed demo-only during sign-off (AC7). This tab surfaces demo-reset and mock-state editing tools that do not exist in production. No remediation work. | n/a | n/a |
| profile/index | `lovable/screenshots/CBY_ADMIN /profile.png` | `frontend/app/pages/profile.vue` | desktop | MISSING | Story 6.5 + 7.10 shipped the profile page; Playwright never captured. | 9.4 | patch (verdict provisional pending capture) |
| notifications/index | `lovable/screenshots/CBY_ADMIN /notifications.png` | `frontend/app/pages/notifications.vue` | desktop | MISSING | Story 5.3 shipped the page; never captured by a parity spec. | 9.4 | patch (verdict provisional pending capture) |
| notifications/dropdown | `lovable/screenshots/CBY_ADMIN /notifications-dropdown.png` | `frontend/app/components/NotificationDropdown.vue` | desktop | MISSING | Header dropdown was shipped by 5.3 / 6.7. Never captured. | 9.4 | patch (verdict provisional pending capture) |
| notifications/empty | `lovable/screenshots/CBY_ADMIN /notifications-empty.png` | `frontend/app/pages/notifications.vue` (empty state) | desktop | MISSING | Empty state. Never captured. | 9.4 | patch (verdict provisional pending capture) |
| notifications/bank-admin | `lovable/screenshots/BANK-ADMIN/notifications.png` | `frontend/app/pages/notifications.vue` (BANK_ADMIN viewer) | desktop | MISSING | Never captured. | 9.4 | patch (verdict provisional pending capture) |
| customs/issue | `lovable/screenshots/COMMITTEE_DIRECTOR/customs-issue-page.png` | `frontend/app/pages/customs.vue` and customs issuance flow | desktop | MISSING | Story 3.6 + 5.7 + 6.7 shipped customs issuance + print page. Never captured at the parity step. | 9.3 | patch (verdict provisional pending capture) |
| auth/access-denied | `lovable/screenshots/COMMITTEE_DIRECTOR/role-access-denied.png` | `frontend/app/middleware/role.global.ts` redirect / fallback page | desktop | SKIP | This is a Lovable demo affordance — the role switcher lets a user pretend to be the wrong role and see the access-denied page. Real production cannot be accessed by the wrong role because middleware redirects before render. Demo-only surface. | n/a | n/a |

---

## SKIP Rows — Demo-only Exclusions (AC7)

These rows are explicit demo-only exclusions per `AGENTS.md` §Prototype-Only Demo Features. They live in the matrix for AC5 coverage but get **no remediation work**.

1. `auth/access-denied` — demo role-switcher artefact (see row above).
2. `settings/tab-6` — confirmed demo-only during sign-off; hosts Lovable Demo Controls / mock-state editing tools with no production equivalent.
3. (Lovable `RoleSwitcher` in header) — not represented by a single PNG row; SKIP applied at the layout level.

Three additional rows remain pending a product call before their MISSING/SKIP classification can be finalized:
- `requests/detail-note` — admin note overlay (Lovable-only operator surface vs. production feature?)
- `requests/detail-voting-duplicate-invoice` — duplicate-invoice warning pill (backend mock vs. real signal?)
- `requests/detail-view-file` — inline PDF preview vs. open-in-new-tab (product preference?)

---

## Provisional Verdict Caveat (read before sign-off)

34 rows are marked `MISSING` because the Epic 7 Playwright suite never wrote a `current.png` for them, even though the pages exist in production (Stories 5.x, 6.x, 7.10, 8.x). These are not necessarily drift findings — they are **parity-coverage gaps** that block both 9.3/9.4 remediation and 9.5 visual-regression lock-in. Story 9.4's first task should be to extend the Playwright parity specs to write baselines for:

- `frontend/tests/screenshots/7-7/` — `banks.vue` standalone, `cby-staff.vue` edit modal, `workflow-docs.vue`
- `frontend/tests/screenshots/7-8/` — `reports.vue` per role
- `frontend/tests/screenshots/7-9/` — `audit/index.vue` per tab
- `frontend/tests/screenshots/7-10/` — `settings.vue` per tab + `profile.vue`
- `frontend/tests/screenshots/7-1/` — `login.vue` dark variant
- `frontend/tests/screenshots/7-4/` — outstanding detail variants (parties-customs, actions-expanded, executive-final-rejected)

Once those baselines exist, re-run this matrix's verdicting loop against the fresh triplets and downgrade provisional MISSING rows to their real verdict (likely PASS or MINOR_DRIFT given those stories already shipped).

---

## Sign-Off Block

> _The sign-off line below is the AC9 gate. Add it (or a returned-for-corrections note) before Stories 9.3 and 9.4 may begin._

```
Signed off: <YYYY-MM-DD> by <user>
```

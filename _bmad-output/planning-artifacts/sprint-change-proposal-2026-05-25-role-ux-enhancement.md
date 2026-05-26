# Sprint Change Proposal — Role-Driven UX/UI Enhancement (Epic 12)

Date: 2026-05-25
Project: Yemen Flow Hub
Workflow: `bmad-correct-course`
Mode: Batch, scope pre-approved by user in chat
Author: Claude Code (Opus 4.7) for MAJED

---

## 1. Issue Summary

On 2026-05-25, eight enterprise-grade per-role UX specifications were authored in `docs/user-view/`:

- `bank-admin.md` (28 KB)
- `bank-reviewer.md` (28 KB)
- `cby-admin.md` (85 KB)
- `committee-director.md` (36 KB)
- `data-entry.md` (37 KB)
- `executive-member.md` (27 KB)
- `support-committee.md` (28 KB)
- `swift-officer.md` (25 KB)

These documents specify, per role: operational posture, scope and boundary, workflow authority, document authority, sidebar navigation, dashboard structure (KPIs, queues, empty/loading/error states, density, micro-copy), per-page interaction patterns, status presentation rules, accessibility, RTL behavior, and non-visibility contracts.

They were authored **after** Epic 11.1 (Role Surface Authority Matrix and Navigation Contract) shipped its first cut, and **after** Epic 10 transplanted the Lovable UI. They represent a substantial uplift over what currently ships:

- Epic 7 (Dashboard UI by role) delivered role-distinct dashboards but at a coarser fidelity than the per-role behavioral, density, and micro-copy detail in `docs/user-view/`.
- Epic 10 transplanted Lovable pages but Lovable predates these role specs and does not encode the operational posture differences (task-oriented vs governance-oriented vs read-only oversight vs claim-aware) that `docs/user-view/` makes explicit.
- Epic 11 enforces *what is shown vs hidden* per role (the authority matrix). It does not enforce *how the visible surface should feel and behave* per role (the operational UX).

The result: every production role today renders technically-correct pages, but the surfaces do not yet embody the role's operational posture as specified. CBY_ADMIN looks like an operator console rather than a governance/oversight surface; DATA_ENTRY mixes simplified business labels with raw operational hints; COMMITTEE_DIRECTOR lacks the external-FX-pending workload framing the spec calls for; SUPPORT_COMMITTEE claim states are present but not designed for the presence-based posture the spec describes; etc.

This is a UX-uplift correction, not a workflow correction. Backend behavior, status enums, and Epic 11 authority matrix are not in scope.

## 2. Change Navigation Checklist Results

| Item | Status | Result |
| --- | --- | --- |
| 1.1 Trigger | Done | User instruction on 2026-05-25 to use `docs/user-view/` to enhance app UX/UI via `/ui-ux-pro-max`, creating an epic and stories to achieve it. |
| 1.2 Core problem | Done | New requirement category: eight role-specific operational-posture UX specs that the shipped UI does not yet fully embody. Categorization: **new requirement emerged from stakeholders** — `docs/user-view/` was authored after the closing of Epics 7, 10, and the start of Epic 11. |
| 1.3 Evidence | Done | (a) Eight `docs/user-view/*.md` files dated 2026-05-25, totalling ~295 KB of role-specific behavioral specification; (b) sprint-status.yaml shows Epic 11 partially done — only authority matrix story in-progress, dashboard/detail alignment (11.3) still backlog; (c) Lovable transplant (Epic 10) closed before these specs existed. |
| 2.1 Current epic viability | Done | Epic 11 remains viable as-is. It addresses *authority/non-visibility*, not *operational posture per role*. Splitting these concerns avoids scope creep on the in-flight 11.1 story. |
| 2.2 Epic-level changes | Done | Add **Epic 12: Role-Driven UX/UI Enhancement** as a new corrective epic. Do not reopen Epics 7, 10, or 11. |
| 2.3 Future epics | Done | No active future epic in the backlog conflicts. Epic 11 stories 11.2/11.3/11.4 remain valid; Epic 12 will sequence *after* 11.1 ships the authority matrix so the UX work has a stable role-rendering contract to build on. |
| 2.4 New epic necessity | Done | Necessary. Mixing per-role UX uplift with Epic 11 (governance) or Epic 11.3 (alignment) would conflate behavioral fidelity with authority enforcement and would risk re-litigating non-visibility rules during UX polish reviews. |
| 2.5 Priority | Done | After Epic 11.1 (authority matrix) and Epic 11.2 (external FX) ship. Story 11.3 may either be folded into Epic 12 Tier-1 or kept separate; recommendation: keep 11.3 as the alignment baseline and treat Epic 12 as the enhancement-on-top layer. |
| 3.1 PRD impact | Done | PRD objectives unchanged. Authoritative source list (already amended for `roles-reference.md` and `testing-playbook.md`) should now also reference `docs/user-view/` as the per-role UX authority. |
| 3.2 Architecture impact | N/A | No backend, schema, API, or state-machine change. Frontend remains Nuxt 4 / Vue / shadcn-vue / Tailwind v4 / Pinia stack. |
| 3.3 UI/UX impact | Done | Major: every role's sidebar grouping, dashboard widget set, density, micro-copy, KPI semantics, empty/loading/error states, per-page interaction patterns, and status-presentation rules are now specified in detail and must be brought into the shipped frontend. |
| 3.4 Secondary artifacts | Action-needed | (a) `AGENTS.md` source-of-truth list: add `docs/user-view/` after `testing-playbook.md`; (b) `epics.md`: add Epic 12; (c) `sprint-status.yaml`: add Epic 12 backlog; (d) parity-evidence rules from Epic 9 must continue to apply — each Epic 12 story declares its triplet (spec source + visual reference + diff). |
| 4.1 Direct adjustment | Viable | Add Epic 12 with three priority-tiered stories, executed via standard create-story / dev-story / code-review cycle, with `/ui-ux-pro-max` invoked during dev to inform design choices. Effort: medium-high. Risk: low (no backend, no enum, no migration). |
| 4.2 Rollback | Not viable | Nothing to roll back; the deficit is missing-fidelity, not incorrect work. |
| 4.3 MVP review | Not viable | MVP scope is intact. This is post-MVP institutional UX uplift. |
| 4.4 Recommended path | Done | Direct adjustment via new Epic 12. |
| 5.1–5.5 Proposal | Done | This document. |
| 6.1–6.4 Final handoff | Pending user approval | Once approved: amend `AGENTS.md`, append Epic 12 to `epics.md`, add Epic 12 to `sprint-status.yaml`, then run `bmad-create-epics-and-stories` to materialise the three stories. |

## 3. Impact Analysis

### Epic Impact

**Add Epic 12: Role-Driven UX/UI Enhancement.**

Epic 12 does not reopen Epics 1–11. It layers per-role operational-posture fidelity on top of:

- the Epic 11.1 role-surface authority matrix (what is rendered),
- the Epic 11.3 dashboard/request-detail alignment baseline (correct content per role),
- the Epic 9 parity-evidence triplet gate (spec → visual → diff).

Epic 12 specifies *how each visible surface should look, feel, and behave* per role's operational posture.

### Story Impact

Three priority-tiered stories, sequenced by operational traffic and platform criticality:

| Story | Tier | Roles | Rationale |
| --- | --- | --- | --- |
| 12.1 | Tier 1 — High-traffic operational | DATA_ENTRY, BANK_REVIEWER, SUPPORT_COMMITTEE, EXECUTIVE_MEMBER | These four roles drive the day-to-day workflow throughput. They have the highest session frequency and the strongest operational-posture differentiation (task-oriented intake, review gating, claim-aware presence review, voting). |
| 12.2 | Tier 2 — Administrative | CBY_ADMIN, BANK_ADMIN | Governance and bank-side administration. Lower session frequency, higher density, oversight tone, broader KPI surfaces. |
| 12.3 | Tier 3 — Lifecycle finalization | COMMITTEE_DIRECTOR, SWIFT_OFFICER | Lowest session frequency, highest stage-specific surface specialisation (voting lifecycle controls + external FX completion; SWIFT upload + FX confirmation request). Depends on Epic 11.2 external FX migration. |

Each story:

- Sources its role specs from `docs/user-view/<role>.md`.
- Sources visual references from existing Lovable screenshots and current frontend screens (parity-evidence rule).
- Invokes `/ui-ux-pro-max` during dev to apply design-system intelligence (typography, density, hover, motion, RTL, micro-copy) without inventing new components — the existing shadcn-vue + Tailwind v4 + design tokens remain the constraint.
- Produces the Epic 9 parity-evidence triplet (spec citation + lovable/current-screen reference + diff).
- Adds Vitest role-specific assertions and Playwright visual baselines per surface.

### Artifact Conflicts

Affected artifacts:

- `AGENTS.md` — source-of-truth list: insert `docs/user-view/` after `testing-playbook.md`.
- `_bmad-output/planning-artifacts/epics.md` — append Epic 12 with three stories.
- `_bmad-output/implementation-artifacts/sprint-status.yaml` — append Epic 12 and three stories with `backlog` status; Story 12.3 marked `blocked-by: 11-2`.
- `_bmad-output/implementation-artifacts/sprint-N-*-plan.md` — a new `sprint-12-role-ux-enhancement-plan.md` produced later by `bmad-sprint-planning`.

No `docs/` files are modified by Epic 12 itself — `docs/user-view/` is the *input* not the *output*.

### Technical Impact

- No backend changes. No schema, enum, migration, API, or service changes.
- No new frontend dependencies. Continue using Tailwind v4, shadcn-vue, Pinia, VueUse, VeeValidate, Zod.
- New Vitest assertions: per-role surface contracts (KPI presence, density classes, micro-copy strings, empty/loading/error state markers).
- New Playwright baselines: per-role dashboard + per-role request-detail variants.
- Parity-evidence triplets: tracked under `docs/ui-parity/` (extends Story 9.2 matrix).

## 4. Recommended Approach

**Direct Adjustment** — add Epic 12 with three priority-tiered stories. Execute after Epic 11.1 ships its authority matrix and Epic 11.2 ships the external FX migration (Tier-3 dependency only). Tier 1 and Tier 2 may start immediately after 11.1 ships.

Sequencing:

1. Land Epic 11.1 (in-progress).
2. Land Epic 11.3 baseline alignment (current backlog) — establishes per-role dashboard/detail correctness floor.
3. Story 12.1 — Tier 1 operational roles uplift.
4. Story 12.2 — Tier 2 administrative roles uplift.
5. Land Epic 11.2 external FX migration.
6. Story 12.3 — Tier 3 finalization roles uplift (depends on 11.2 for Director external-FX surfaces; SWIFT can begin earlier on non-FX surfaces).

This preserves the in-flight governance work, avoids duplicating fidelity work, and prevents the Tier-3 surfaces from being polished against legacy customs-declaration terminology that 11.2 is replacing.

## 5. Detailed Change Proposals

### 5.1 `AGENTS.md` — source-of-truth list

OLD:

```md
1. roles-reference.md — production role responsibilities, visibility, dashboard surfaces, and role-specific non-visibility rules
2. testing-playbook.md — role smoke tests, lifecycle handoff tests, document access checks, and end-to-end QA expectations
3. docs/01-workflow-and-business-rules.md — Workflow stages, business rules, status enums
...
```

NEW:

```md
1. roles-reference.md — production role responsibilities, visibility, dashboard surfaces, and role-specific non-visibility rules
2. testing-playbook.md — role smoke tests, lifecycle handoff tests, document access checks, and end-to-end QA expectations
3. docs/user-view/*.md — enterprise-grade per-role UX specifications (operational posture, dashboard structure, page interaction patterns, status presentation, density, micro-copy, RTL, non-visibility)
4. docs/01-workflow-and-business-rules.md — Workflow stages, business rules, status enums
...
```

Rationale: `docs/user-view/` is the authoritative source for per-role UX behaviour. Place it above the architecture docs because it constrains rendering decisions that the architecture docs do not.

### 5.2 `_bmad-output/planning-artifacts/epics.md` — append Epic 12

Insert after the existing Epic 11 section (after line 3304):

```md
---

## Epic 12: Role-Driven UX/UI Enhancement

**Purpose:** Bring the shipped frontend up to the per-role operational-posture fidelity specified in `docs/user-view/`. Epic 7 delivered role-distinct dashboards; Epic 10 transplanted Lovable pages; Epic 11.1 enforced what is rendered per role; this epic enforces *how the visible surface should look, feel, and behave* per role's operational posture.

**Decision date:** 2026-05-25

**Source authorities:**
1. `docs/user-view/*.md` — final authority for per-role operational posture, dashboard structure, page interaction patterns, KPI semantics, density, micro-copy, status presentation, empty/loading/error states, and RTL behaviour.
2. `roles-reference.md` — non-visibility and visibility contract (must remain consistent with Epic 11.1 matrix).
3. `testing-playbook.md` — role smoke and lifecycle assertions.
4. `DESIGN.md` — visual token constraints (typography, colour, spacing, motion). New UX must compose existing tokens; no new tokens introduced by this epic.
5. Existing lovable/ screenshots and current frontend screens — parity-evidence visual baselines (Epic 9 triplet rule).

**Correction rules:**
- Compose existing shadcn-vue components and `DESIGN.md` tokens. Do not introduce new design primitives.
- Every story produces the Epic 9 parity-evidence triplet: spec citation + visual reference + diff.
- Operational-posture uplift must not loosen Epic 11.1 non-visibility contracts.
- External FX completion surfaces (Director + SWIFT) must use Epic 11.2 terminology, not legacy customs wording.
- Use `/ui-ux-pro-max` during dev for design intelligence; do not invent new styles.

**Common technical requirements for all Epic 12 stories:**
- Run SocratiCode before modifying existing files: `codebase_search`, then `codebase_symbol` and `codebase_impact` for touched components.
- Use browser verification (dev-browser) for UI-facing changes.
- Add Vitest role-specific assertions and Playwright visual baselines per surface.
- Update `docs/ui-parity/parity-matrix.md` (Story 9.2 artefact) with triplets for each touched surface.
- Keep `_bmad-output/implementation-artifacts/`, `_bmad-output/test-artifacts/`, and `graphify-out/` local-only and unstaged.

---

### Story 12.1: Tier 1 — High-Traffic Operational Roles UX Uplift

As a daily operational user (DATA_ENTRY, BANK_REVIEWER, SUPPORT_COMMITTEE, or EXECUTIVE_MEMBER),
I want my dashboard and primary work surfaces to embody my role's operational posture as specified in `docs/user-view/<role>.md`,
So that the product feels like a focused work surface tuned to my job rather than a generic admin console.

**Source authority:**
- `docs/user-view/data-entry.md`
- `docs/user-view/bank-reviewer.md`
- `docs/user-view/support-committee.md`
- `docs/user-view/executive-member.md`
- `roles-reference.md` (non-visibility cross-check)
- `frontend/app/pages/dashboard.vue` and role-specific dashboard components
- `frontend/app/pages/requests/index.vue` and `frontend/app/pages/requests/[id]/index.vue`
- Wizard, ActionsPanel, DocumentChecklist, VotingPanel, support claim, and inactivity-banner components

**Targets:**
- DATA_ENTRY: task-oriented intake surface; simplified business-status presentation; returned-queue prominence; document checklist density and validation tone per spec.
- BANK_REVIEWER: review-gate posture; submitted queue prominence; clear separation between self-bank visibility and CBY downstream tracking; ActionsPanel decision affordances per spec.
- SUPPORT_COMMITTEE: claim-aware presence posture; queue ↔ claim distinction; claim-by-me / claim-by-other / unclaimed visual states; release/heartbeat surface cues per spec.
- EXECUTIVE_MEMBER: voting-focused posture; voting queue + closed-decisions framing; vote affordances and justification flow per spec.

**Acceptance criteria:**
- Each of the four roles' dashboard surfaces match the spec for: KPI set, KPI ordering, density, empty/loading/error states, micro-copy, primary CTA presence, and quick-action set.
- Request-list surface per role matches spec for: tab/filter set, default tab, status-label presentation (simplified business labels for DATA_ENTRY), bulk action visibility, and empty-state copy.
- Request-detail surface per role matches spec for: tab order, ActionsPanel rendering rules, document checklist scoping, support-claim presence states (SUPPORT only), voting panel framing (EXECUTIVE only).
- Status presentation: DATA_ENTRY sees simplified business labels; other three roles see canonical workflow labels.
- Non-visibility holds: no SWIFT, no FX, no admin, no governance surfaces leak into these four roles.
- Parity-evidence triplet recorded per surface in `docs/ui-parity/parity-matrix.md`.
- Vitest role-specific assertions cover spec micro-copy, KPI presence/order, density classes.
- Playwright baselines updated for the four dashboards and four request-detail variants.
- `/ui-ux-pro-max` invoked during dev; design rationale captured in the story validation report.

**Out of scope:** Tier 2 admin roles, Tier 3 finalization roles, backend changes, new design tokens, FX terminology (deferred to 11.2 and 12.3).

---

### Story 12.2: Tier 2 — Administrative Roles UX Uplift

As an administrator (CBY_ADMIN or BANK_ADMIN),
I want my dashboard and admin surfaces to embody the oversight/governance posture specified in `docs/user-view/<role>.md`,
So that I quickly answer platform-health and bank-operations questions rather than navigating an operator console.

**Source authority:**
- `docs/user-view/cby-admin.md` (85 KB — the most detailed spec)
- `docs/user-view/bank-admin.md`
- `roles-reference.md` (CBY_ADMIN read-only oversight contract; BANK_ADMIN bank-internal authority contract)
- `frontend/app/pages/dashboard.vue` and admin-role dashboard components
- `frontend/app/pages/admin/*.vue` (entities, cby-staff, workflow-docs, roles)
- `frontend/app/pages/staff.vue` (BANK_ADMIN staff management)
- Reports and audit surfaces (CBY_ADMIN)

**Targets:**
- CBY_ADMIN dashboard: strategic governance surface — KPI strip (system-health, bottleneck, risk, executive-decision-delay, compliance-anomaly metrics), bank filter + date filter toolbar, export Executive Summary PDF, no New Request CTA, "إشراف فقط" read-only oversight badge.
- CBY_ADMIN admin surfaces (entities, cby-staff, workflow-docs, roles): governance tone, full-bank visibility, density and micro-copy per spec, no workflow action affordances leak into oversight pages.
- BANK_ADMIN dashboard: bank-internal operations posture — bank-scoped KPIs, sparkline trend, quick-action, recent requests table.
- BANK_ADMIN staff management: bank-scoped staff CRUD, role assignment limited to BANK_REVIEWER / DATA_ENTRY / SWIFT_OFFICER, deactivation flow.

**Acceptance criteria:**
- CBY_ADMIN dashboard renders the governance KPI strip and toolbar per spec; no operational action buttons leak.
- CBY_ADMIN admin pages match spec density, micro-copy, and empty/loading/error states.
- BANK_ADMIN dashboard renders bank-scoped KPIs and recent-requests table per spec.
- BANK_ADMIN staff management enforces bank-scoped role allowlist and matches spec for modal/dialog tone.
- Non-visibility holds: CBY_ADMIN must not see workflow action buttons; BANK_ADMIN must not see CBY-side admin surfaces.
- Parity-evidence triplet recorded per surface.
- Vitest assertions cover oversight-badge presence, action-button absence, role-allowlist.
- Playwright baselines updated for the two dashboards and key admin pages.
- `/ui-ux-pro-max` invoked during dev.

**Out of scope:** Tier 1 operational roles, Tier 3 finalization roles, backend changes, new analytics endpoints (reuse existing `GET /api/dashboard/stats` and reports).

---

### Story 12.3: Tier 3 — Lifecycle Finalization Roles UX Uplift

As a lifecycle-finalization user (COMMITTEE_DIRECTOR or SWIFT_OFFICER),
I want my dashboard and stage-specific surfaces to embody the role-specific finalization posture specified in `docs/user-view/<role>.md`,
So that voting lifecycle management, external FX completion, and SWIFT + FX-confirmation-request upload feel like first-class workflows rather than generic detail pages.

**Source authority:**
- `docs/user-view/committee-director.md`
- `docs/user-view/swift-officer.md`
- `roles-reference.md` (Director governance authority; SWIFT scope)
- Epic 11.2 external FX migration outputs (status enum, document model/API naming, PDF generation)
- `frontend/app/pages/dashboard.vue` and finalization-role dashboard components
- VotingPanel, ActionsPanel director controls, external FX surfaces, SWIFT upload page

**Dependencies:**
- **Blocked by Story 11.2** (external FX confirmation status and terminology migration). Director external-FX surfaces must be polished against post-migration terminology, not legacy customs wording.

**Targets:**
- COMMITTEE_DIRECTOR dashboard: governance + lifecycle posture — voting-open queue, voting-closed-awaiting-finalize queue, external-FX-pending workload (post 11.2), recent finalized decisions, decision-delay metric.
- COMMITTEE_DIRECTOR request-detail: voting-session controls (open/close), tie-break affordance, override-and-finalize affordance with justification, external FX completion affordances (download generated PDF, upload signed/stamped PDF).
- SWIFT_OFFICER dashboard: focused upload queue posture — SUPPORT_APPROVED queue, awaiting-FX queue (post 11.2), uploads completed today.
- SWIFT_OFFICER request-detail: SWIFT upload affordance + FX confirmation request upload affordance (post 11.2) gated to SUPPORT_APPROVED status.

**Acceptance criteria:**
- COMMITTEE_DIRECTOR dashboard surfaces the voting lifecycle queues and external-FX-pending workload per spec.
- VotingPanel + ActionsPanel director controls match spec tone, density, and confirmation flows.
- External FX completion surfaces (Director side) use post-11.2 terminology end-to-end; no legacy customs wording in user-facing copy.
- SWIFT_OFFICER dashboard surfaces SWIFT-relevant queues only; no voting / FX-completion / admin surfaces leak.
- SWIFT upload + FX-confirmation-request upload surfaces (SWIFT side) match spec.
- Non-visibility holds per `roles-reference.md`.
- Parity-evidence triplet recorded per surface.
- Vitest assertions cover director-only and SWIFT-only affordance presence/absence; post-11.2 terminology.
- Playwright baselines updated for the two dashboards and finalization request-detail variants.
- `/ui-ux-pro-max` invoked during dev.

**Out of scope:** Tier 1 operational roles, Tier 2 administrative roles, backend changes, the 11.2 migration itself (this story consumes its outputs).
```

### 5.3 `_bmad-output/implementation-artifacts/sprint-status.yaml` — append Epic 12

Insert at the end of the file:

```yaml
  # ─── Epic 12: Role-Driven UX/UI Enhancement ──────────────────────────────
  # Per-role operational-posture fidelity from docs/user-view/.
  # Tiered by traffic and stage-specialisation; uses /ui-ux-pro-max during dev.
  epic-12: backlog
  12-1-tier-1-high-traffic-operational-roles-ux-uplift: backlog
  12-2-tier-2-administrative-roles-ux-uplift: backlog
  12-3-tier-3-lifecycle-finalization-roles-ux-uplift: backlog  # blocked-by: 11-2
  epic-12-retrospective: optional
```

## 6. Implementation Handoff

Scope classification: **Moderate** — backlog reorganization + new epic + three stories, but no architectural or workflow-rule change.

Recommended next BMAD commands, in order:

1. **Apply the three artefact edits above** (AGENTS.md source list, epics.md Epic 12 section, sprint-status.yaml Epic 12 block) — Developer agent (this session).
2. **`bmad-create-epics-and-stories`** — materialise the three Epic 12 story files at `_bmad-output/implementation-artifacts/12-1-*.md`, `12-2-*.md`, `12-3-*.md` with full context (story spec, source-authority cites, acceptance criteria, parity-evidence rule, /ui-ux-pro-max activation note, dependencies).
3. **`bmad-sprint-planning`** — produce `sprint-12-role-ux-enhancement-plan.md` once Epic 11.1 ships and 11.3 is scheduled.
4. **Story cycle per story** — `bmad-create-story 12-1-…` → `bmad-dev-story` (invoke `/ui-ux-pro-max` during dev) → `bmad-code-review` → next story.

## 7. Success Criteria

The correction is complete when:

- Every production role's primary surfaces (dashboard, request list, request detail, role-specific admin/finalization pages) match the operational-posture detail in `docs/user-view/<role>.md`.
- Parity-evidence triplets exist for every touched surface and are linked from `docs/ui-parity/parity-matrix.md`.
- Vitest role-specific assertions and Playwright baselines cover all eight roles' enhanced surfaces.
- No Epic 11.1 non-visibility contract is loosened by the uplift.
- All Director/SWIFT external-FX surfaces use post-Epic-11.2 terminology.
- No new design tokens, components, or backend endpoints were introduced.

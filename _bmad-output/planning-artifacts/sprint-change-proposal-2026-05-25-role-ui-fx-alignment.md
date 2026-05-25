# Sprint Change Proposal - Role UI and External FX Alignment

Date: 2026-05-25
Project: Yemen Flow Hub
Workflow: `bmad-correct-course`
Mode: Batch, approved by user in chat

## 1. Issue Summary

The project now has two new practical reference documents: `roles-reference.md` and `testing-playbook.md`. They clarify that Yemen Flow Hub is not a shared dashboard with hidden/disabled controls. It is eight role-specific operational workspaces. Each role must receive a different rendered product surface: dashboard, navigation, request-list emphasis, document access, workflow actions, and non-visibility rules.

The same references also replace the older final-stage "customs declaration" terminology with external FX confirmation (`تأكيد مصارفة خارجية`) and introduce the `FX_CONFIRMATION_PENDING` handoff after `SWIFT_UPLOADED`.

Current code and older planning artifacts remain partially aligned but inconsistent:

- `frontend/app/pages/dashboard.vue` renders role-specific dashboard components.
- `frontend/app/constants/workflow.ts` has role buckets, route role maps, and Data Entry business status abstraction.
- `frontend/app/components/AppSidebar.vue` uses separate hardcoded role logic and does not fully follow the canonical route/nav contract.
- Backend/frontend enums still use `CUSTOMS_DECLARATION_ISSUED`; the new testing path expects `FX_CONFIRMATION_PENDING`.
- Existing docs and story artifacts still contain legacy customs terminology.

## 2. Change Navigation Checklist Results

| Item | Status | Result |
| --- | --- | --- |
| 1.1 Trigger story | Done | User supplied `roles-reference.md` and `testing-playbook.md` and asked to align the project with role-specific UI behavior. |
| 1.2 Core problem | Done | New requirement and source-of-truth clarification: role-specific UI surfaces must be rendered per role, not merely protected by backend checks; external FX terminology replaces customs wording. |
| 1.3 Evidence | Done | Direct references in `roles-reference.md` lines around role dashboards/non-visibility and `testing-playbook.md` lifecycle path through `FX_CONFIRMATION_PENDING`; code search shows current sidebar/customs/status drift. |
| 2.1 Current epic viability | Done | Previous epics can remain historically done, but none should be reopened as-is because this is a cross-cutting governance correction. |
| 2.2 Epic-level changes | Done | Add a new corrective epic for role-surface governance and external FX migration. |
| 2.3 Future epics | Done | No active future epic in sprint status; Epic 9 exists in `epics.md` but is not present in sprint status and targets visual parity enforcement, not workflow terminology/RBAC surface governance. |
| 2.4 New epic necessity | Done | New epic is needed to avoid mixing visual parity remediation with role authority and final-stage workflow semantics. |
| 2.5 Priority | Done | Run before further UI implementation stories; otherwise new UI may continue duplicating stale role/customs assumptions. |
| 3.1 PRD impact | Done | Product objective unchanged; source hierarchy and acceptance criteria need update. |
| 3.2 Architecture impact | Action-needed | Backend enum, transition map, document model/API naming, PDF generation, and audit action naming need migration design. |
| 3.3 UI/UX impact | Done | Navigation, dashboards, request detail actions, document checklist, SWIFT route, Director final workflow, and admin surfaces need role-by-role audit. |
| 3.4 Secondary artifacts | Action-needed | AGENTS.md, epics.md, sprint-status.yaml, docs/01, docs/03, docs/04, docs/06, tests, and possibly migration docs must be aligned. |
| 4.1 Direct adjustment | Viable | Add corrective epic and implement in sequenced stories. Effort medium-high, risk medium. |
| 4.2 Rollback | Not viable | Rolling back completed epics would discard useful implementation without resolving source-of-truth drift. |
| 4.3 MVP review | Not viable | Scope remains valid; this is governance/terminology alignment, not MVP reduction. |
| 4.4 Recommended path | Done | Direct adjustment with a new Epic 11. |
| 5.1-5.5 Proposal | Done | This document defines issue, impact, edits, and handoff. |
| 6.1-6.4 Final handoff | Done | AGENTS.md, epics.md, and sprint-status.yaml updated locally for the next story cycle. |

## 3. Impact Analysis

### Epic Impact

Add Epic 11: Role Surface Governance and External FX Confirmation Alignment.

This epic does not reopen completed Epics 1-10. It creates a correction layer that audits and fixes current production behavior against the new role reference and test playbook.

### Story Impact

The next story should not be visual polish. It should first create the authoritative role-surface matrix and wire navigation/page guards/actions to it. External FX migration should be planned as a separate backend/frontend story because status migration touches workflow semantics, documents, PDF generation, resources, tests, and old data.

### Artifact Conflicts

Affected artifacts:

- `AGENTS.md`: source-of-truth order and final workflow terminology.
- `docs/01-workflow-and-business-rules.md`: lifecycle path and final-stage naming.
- `docs/03-database-and-models.md`: canonical status enum and schema terminology.
- `docs/04-frontend-guide.md`: role-surface rendering rules and navigation matrix.
- `docs/06-api-reference.md`: external FX endpoints and document permissions.
- `_bmad-output/planning-artifacts/epics.md`: new Epic 11.
- `_bmad-output/implementation-artifacts/sprint-status.yaml`: new Epic 11 backlog.

### Technical Impact

- Frontend needs a single role visibility contract used by sidebar, route middleware, page quick actions, request detail actions, document checklist, search, and dashboard quick actions.
- Backend needs authoritative role/status enforcement to remain final authority; UI non-rendering is not a security boundary.
- External FX confirmation may require a database/data migration from `CUSTOMS_DECLARATION_ISSUED` and `customs_declarations` naming to an external-FX concept or a compatibility alias.
- Tests must assert both visibility and non-visibility per role.

## 4. Recommended Approach

Use Direct Adjustment.

Create Epic 11 and execute it in this order:

1. Role UI authority matrix and navigation/action rendering audit.
2. External FX confirmation status/terminology migration design and implementation.
3. Role dashboard/request-detail alignment against `roles-reference.md`.
4. Role smoke and lifecycle automation from `testing-playbook.md`.

This preserves completed work while correcting the governance model before more UI stories are implemented.

## 5. Detailed Change Proposals

### AGENTS.md

OLD:

```md
1. docs/01-workflow-and-business-rules.md
2. docs/03-database-and-models.md
...
```

NEW:

```md
1. roles-reference.md
2. testing-playbook.md
3. docs/01-workflow-and-business-rules.md
...
```

Rationale: the user explicitly approved these files as the new practical role/UI and QA alignment reference.

### epics.md

Add Epic 11 with four stories:

- 11.1 Role Surface Authority Matrix and Navigation Contract
- 11.2 External FX Confirmation Status and Terminology Migration
- 11.3 Role Dashboard and Request Detail Alignment
- 11.4 Role Smoke and Lifecycle Test Automation

### sprint-status.yaml

Add Epic 11 and all four stories as backlog.

## 6. Implementation Handoff

Scope classification: Moderate.

Recommended next BMAD command:

```text
bmad-create-story 11-1-role-surface-authority-matrix-navigation-contract
```

Story 11.1 should be planning-heavy but implementation-ready. It should produce or update the canonical role-surface matrix and then wire frontend navigation/action rendering to that matrix without changing backend workflow behavior.

## 7. Success Criteria

The correction is complete when:

- Every production role has a documented visible and non-visible UI contract.
- Sidebar, route guards, dashboard quick actions, request detail actions, document downloads, and search shortcuts all use that contract.
- External FX confirmation has replaced customs terminology in user-facing Director/SWIFT completion flows.
- Backend still rejects unauthorized direct API calls even where UI is not rendered.
- `testing-playbook.md` has corresponding automated or scripted smoke coverage for role visibility, lifecycle handoffs, document permissions, and immutable states.

# Sprint Change Proposal — Lovable 1:1 UI Parity Rework

Date: 2026-05-19
Project: Yemen Flow Hub
Workflow: `bmad-correct-course`
Mode: Batch, approved by user in chat before artifact update

## 1. Issue Summary

The current parity plan treated the Lovable prototype as a broad functional and route reference. That closed many routes, APIs, and UI states, but it did not force the Nuxt frontend to match the Lovable React UI screenshot-by-screenshot.

The user clarified the required acceptance standard:

- `lovable/screenshots/` is the final visual authority, even when it conflicts with `DESIGN.md`.
- `DESIGN.md` must be updated when screenshots prove a mismatch.
- "1:1" means same layout, spacing, typography, colors, component states, responsive behavior, and no obvious screenshot difference.
- Production UI must use real Laravel APIs. If no API exists, the backend API must be created.
- shadcn-vue must be the component base, with customization to match Lovable.
- Demo-only prototype features stay excluded.
- Stories must be split by screen, with role-specific screenshot matrices inside each story.

## 2. Change Navigation Checklist Results

| Item | Status | Result |
| --- | --- | --- |
| 1.1 Trigger story | Done | Epic 6 / Story 5.7 parity work exposed that "prototype parity" was interpreted as feature parity, not visual 1:1 parity. |
| 1.2 Core problem | Done | Misunderstanding of original requirements and failed approach requiring a stricter execution model. |
| 1.3 Evidence | Done | Existing stories and sprint status show parity items marked done while the user reports repeated visual mismatch against Lovable. |
| 2.1 Current epic viability | Done | Epic 6 can remain as functional production-readiness work, but cannot be the final visual acceptance gate. |
| 2.2 Epic-level changes | Done | Add Epic 7 as a new screen-based 1:1 visual parity epic. |
| 2.3 Future epics | Done | No later epic exists; Epic 7 becomes the next sprint queue. |
| 2.4 New epic necessity | Done | New epic required to avoid rewriting completed functional work while still enforcing visual acceptance. |
| 2.5 Priority | Done | Epic 7 should run next before stakeholder acceptance. |
| 3.1 PRD impact | Done | Product objective unchanged; acceptance quality bar is raised. |
| 3.2 Architecture impact | Done | Backend APIs may need to be added when prototype screens require real data. Authorization and org scoping remain unchanged. |
| 3.3 UI/UX impact | Done | `DESIGN.md`, `docs/08-prototype-gap-analysis.md`, and `epics.md` require updates. |
| 3.4 Secondary artifacts | Done | `sprint-status.yaml` requires Epic 7 backlog entries. |
| 4.1 Direct adjustment | Viable | Add a new acceptance epic and update planning artifacts. Medium effort, low governance risk. |
| 4.2 Rollback | Not viable | Rolling back completed functionality would waste stable backend/frontend work and does not solve visual acceptance. |
| 4.3 MVP review | Not viable | MVP scope remains valid; no need to reduce scope. |
| 4.4 Recommended path | Done | Direct adjustment: add Epic 7 and update design/gap/planning artifacts. |
| 5.1-5.5 Proposal | Done | This proposal defines issue, impacts, changes, and handoff. |
| 6.1-6.5 Final handoff | Done | User approved proceeding; artifacts updated. |

## 3. Impact Analysis

### Epic Impact

Epic 6 remains useful as production-readiness and functional parity work, but it is no longer sufficient for stakeholder acceptance. Epic 7 is added as the strict visual parity pass.

### Story Impact

Future stories must no longer say "match Lovable" generically. Every story must include:

- Lovable React source paths
- Lovable screenshot paths
- Nuxt target paths
- Backend API impact, if data is missing
- Desktop and mobile Playwright screenshot evidence
- Demo-only omission checklist

### Artifact Conflicts

`DESIGN.md` previously declared itself the source of truth. That conflicted with the user's instruction that screenshots win. It now states that `lovable/screenshots/` is final visual authority.

`docs/08-prototype-gap-analysis.md` previously treated in-app role switching as a critical QA gap. That conflicts with the user's instruction that demo-only features remain excluded. It now marks role switching as excluded.

`_bmad-output/planning-artifacts/epics.md` previously included broad Epic 6 parity stories. It now includes Epic 7 with screen-based 1:1 parity stories.

`_bmad-output/implementation-artifacts/sprint-status.yaml` previously had Epic 6 in progress and Story 6.6 in review even though the story file says done. It now marks Epic 6 done and adds Epic 7 backlog.

## 4. Recommended Approach

Use **Direct Adjustment**.

Do not roll back completed work. The current app likely contains useful APIs, pages, state, and tests. The problem is not that every prior implementation is useless; the problem is that acceptance was not screenshot-driven. Epic 7 should reuse current work where possible, but every screen must be re-evaluated against Lovable source and screenshots.

Scope classification: **Moderate**. This requires backlog reorganization and screen-by-screen implementation, but not a product pivot or architecture reset.

## 5. Detailed Change Proposals

### DESIGN.md

OLD:

```md
Source of truth: this file reflects the confirmed stakeholder-approved Lovable prototype.
The implementation must match this file exactly.
```

NEW:

```md
Final visual authority: lovable/screenshots/.
This file codifies the stakeholder-approved Lovable prototype, but screenshots are the final authority for visual parity.
```

Rationale: The user confirmed screenshots win over `DESIGN.md`.

### docs/08-prototype-gap-analysis.md

OLD:

```md
D2 — In-app role switcher for stakeholder QA
Severity: Critical
```

NEW:

```md
D2 — In-app role switcher for stakeholder QA: EXCLUDED
Stakeholder QA should use real authenticated users per role, not UI-only role switching.
```

Rationale: Demo-only prototype features stay excluded.

### epics.md

OLD:

```md
Epic 6: Production Readiness & Full Prototype Parity
```

NEW:

```md
Epic 7: Lovable 1:1 UI Parity Rework
```

Rationale: Epic 6 can remain functional production-readiness work, but acceptance needs a new screen-based visual parity epic.

### sprint-status.yaml

OLD:

```yaml
epic-6: in-progress
6-6-request-detail-voting-panel-parity: review
```

NEW:

```yaml
epic-6: done
6-6-request-detail-voting-panel-parity: done
epic-7: backlog
7-1-appshell-and-login-1-1-parity: backlog
...
7-10-settings-and-profile-1-1-parity: backlog
```

Rationale: Sprint tracker must route the next work to the corrected parity epic.

## 6. Epic 7 Handoff Plan

Recommended next BMAD execution:

1. Run `bmad-create-story 7-1-appshell-and-login-1-1-parity`.
2. Validate the story includes exact Lovable source paths, screenshot paths, Nuxt targets, API impact, and visual verification checklist.
3. Run `bmad-dev-story` for Story 7.1.
4. Verify with Playwright screenshots before code review.
5. Run `bmad-code-review` with visual parity as the primary acceptance lens.

## 7. Success Criteria

Epic 7 succeeds only when every listed screen group has:

- Screenshot-based parity acceptance.
- shadcn-vue-based implementation.
- Real API-backed data.
- Demo-only controls excluded and documented.
- Desktop and mobile screenshot evidence.
- Targeted automated tests for affected code paths.

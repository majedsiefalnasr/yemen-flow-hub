# Documentation Consolidation Plan — Proposal (not executed)

**Evidence date:** 2026-07-12 · **Status:** PLANNING ONLY. No file has been
moved, merged, archived, or deleted as part of this document. Execution
requires separate explicit approval per step (see §9).

**Scope:** ~35 project-knowledge documents (root config docs, `docs/0X-*.md`,
`docs/audit-functional/`, `docs/audit/`, `docs/user-view/`,
`testing-manual/`, `docs/decisions/`, `docs/operations/`,
`docs/release-notes/`). AI-tooling scaffolding (`.agents/skills/`,
`.claude/skills/`, `.superpowers/`, `docs/superpowers/plans+specs/`,
`.github/prompts/`) is explicitly out of scope per your confirmation.

---

## 1. The core problem

Six numbered docs (`docs/00`–`docs/07`) exist as a single linear stack, but
they were written **before** the dynamic workflow engine and patched
**unevenly** by this session's audit. The patch density is inversely
proportional to file number in a confusing way:

- `06-api-reference.md` — patched thoroughly, gold-standard, ~0% stale.
- `03-database-and-models.md` — patched thoroughly, ~30% stale (reprints a
  dead 18-value status enum as if literal schema).
- `02-system-architecture.md` — patched well on backend services, ~40%
  stale (dashboard section describes fixed per-role dashboards, not the
  current two-family model).
- `04-frontend-guide.md` — patched on routes and dashboard-family naming,
  but still contains a full "Voting UI" section (~40 lines) describing a
  removed feature, plus a reprinted dead status enum.
- `01-workflow-and-business-rules.md`, `05-backend-guide.md` — barely
  patched. Both still describe a dedicated "Voting Service"/"Executive
  Voting Stage" as live, current functionality, and `05` lists 4 API route
  families (`/api/voting`, `/api/customs`, `/api/support-review`,
  `/api/workflow`) that `06-api-reference.md` explicitly says don't exist.
- `00-project-brief.md` — least patched, describes a 6-member voting
  committee with tie-break rules in detail, as if current.

`docs/user-view/` (8 files, 6,573 lines) and `testing-manual/` (9 files,
813 lines) both predate the dynamic engine entirely and both still describe
voting UI and the legacy status vocabulary. `testing-manual/` is partially
ahead (uses `/workflows` routes, not `/requests`) — it was evidently touched
later than `docs/user-view/`, which was frozen at the pre-engine snapshot.

`docs/decisions/semantic-mapping.md` documents the `semantic_role`/
`SemanticRegistry` mechanism — central to the current architecture — but
**no numbered core doc references it by name**. This is a documentation gap,
not staleness: the concept exists in code and in one buried ADR, nowhere in
the "read this to understand the system" tier.

`docs/audit-functional/` (22 files, this session's own phase-by-phase audit
trail) and `docs/audit/` (a separate, closed performance/scalability audit,
~20 files) are both audit-genre documents — valuable as historical record,
wrong genre for "how the system works today."

---

## 2. Proposed documentation tree

```
docs/
├── README.md                          NEW — top-level index, "start here"
├── architecture/
│   ├── 01-system-architecture.md      REWRITE of docs/02
│   ├── 02-workflow-engine.md          NEW — merges docs/01's business-rules
│   │                                    core + docs/02's engine-services
│   │                                    section + semantic-mapping.md
│   ├── 03-permission-model.md         NEW — extracted/expanded from
│   │                                    docs/02 + docs/03 + docs/05
│   ├── 04-dashboard-architecture.md   PROMOTE audit-functional/14 to
│   │                                    canonical, rewritten as reference
│   │                                    not decision-record
│   ├── 05-request-state-model.md      NEW — the 4-concept model, merges
│   │                                    docs/03's schema notes + AGENTS.md's
│   │                                    canonical-model section
│   └── 06-database-and-models.md      REWRITE of docs/03 (schema tables
│                                        kept, stale enum block removed)
├── engine/
│   ├── extension-guide.md             NEW — "how to safely add X"
│   └── dynamic-vs-fixed.md            NEW — the philosophy section
├── frontend-guide.md                  REWRITE of docs/04 (voting UI section
│                                        deleted, not preserved)
├── backend-guide.md                   REWRITE of docs/05 (Voting Service
│                                        section deleted, stale API list
│                                        deleted, correct API list points to
│                                        api-reference.md instead of
│                                        duplicating it)
├── api-reference.md                   MOVE docs/06 here basically as-is
│                                        (already the gold standard)
├── auth-and-recovery.md               MOVE docs/07 here as-is
├── development-guide.md               NEW — coding principles, invariants,
│                                        testing conventions, publish rules
├── production-guide.md                NEW — merges operations/runbook.md +
│                                        operations/retention-policy.md +
│                                        audit-functional/21's deployment
│                                        checklist + rollback checklist
├── decisions/
│   └── semantic-mapping.md            KEEP as-is (already good ADR)
├── release-notes/
│   └── wp14-legacy-cleanup.md         KEEP as-is (changelog genre, fine
│                                        where it is)
├── archive/
│   ├── README.md                      NEW — explains what's archived + why
│   ├── project-brief-2026-05.md       ARCHIVE docs/00 (historical framing,
│                                        superseded by architecture/*)
│   ├── audit-functional/              MOVE docs/audit-functional/* here
│   │                                    verbatim (all 22 files — historical
│   │                                    audit trail, valuable, wrong genre
│   │                                    for living docs)
│   ├── audit-performance/             MOVE docs/audit/* here verbatim
│   │                                    (closed performance audit, same
│   │                                    reasoning)
│   ├── user-view/                     MOVE docs/user-view/* here verbatim,
│   │                                    with a banner file (see §5)
│   └── testing-manual/                MOVE testing-manual/* here verbatim,
│                                        OR keep live at repo root if QA
│                                        actively uses it (see §5 — this one
│                                        needs your input, not a unilateral
│                                        archive call)
AGENTS.md                              REWRITE (trim, point at new docs/
                                         tree instead of duplicating content)
DESIGN.md                              KEEP as-is (already current, root
                                         visual design system, not affected
                                         by this consolidation)
CLAUDE.md                              KEEP as-is (thin @AGENTS.md loader)
README.md                              LIGHT EDIT (point at docs/README.md)
backend/CLAUDE.md                      KEEP mostly as-is, fix 1 stale line
                                         (Transition-concurrency section
                                         says VoteType "remain[s] in the
                                         codebase" — Phase F deleted it,
                                         commit 262ef0b4; needs a one-line
                                         update, not a rewrite)
backend/README.md                      KEEP as-is (not reviewed in depth —
                                         out of critical path, low risk)
frontend/CLAUDE.md                     KEEP as-is (already accurate per this
                                         session's Phase D edits)
frontend/DESIGN.md                     KEEP as-is (already current)
frontend/PRODUCT.md                    KEEP as-is (already current)
frontend/SHADCN.md                     KEEP as-is (already current,
                                         mechanical reference genre)
```

---

## 3. Files to keep (as-is or near-as-is)

| File                                        | Why                                                                                                                    |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `DESIGN.md`                                 | Root visual design system — verified current this session, unaffected by RBAC/workflow audit                           |
| `CLAUDE.md`                                 | Thin `@AGENTS.md` loader, nothing to consolidate                                                                       |
| `frontend/CLAUDE.md`                        | Rewritten accurate in Phase D (this session) — routes, dashboard-family model, canonical state model all correct       |
| `frontend/DESIGN.md`                        | Rewritten accurate in Phase D — status-badge section fixed                                                             |
| `frontend/PRODUCT.md`                       | Rewritten accurate in Phase D — strategic principle #2 fixed                                                           |
| `frontend/SHADCN.md`                        | Mechanical component reference, verified current, low staleness risk (component names don't drift with business logic) |
| `docs/06-api-reference.md`                  | Gold-standard, ~0% stale — move location only, no content rewrite needed                                               |
| `docs/07-account-recovery-and-mail.md`      | Fully current, orthogonal topic, move location only                                                                    |
| `docs/decisions/semantic-mapping.md`        | Accurate ADR, keep in place, but link it from the new architecture docs (currently orphaned/unreferenced)              |
| `docs/operations/runbook.md`                | Fully current, becomes an input to `production-guide.md` (merge, not delete — see §4)                                  |
| `docs/operations/retention-policy.md`       | Fully current, becomes an input to `production-guide.md`                                                               |
| `docs/release-notes/wp14-legacy-cleanup.md` | Changelog genre, correctly dated, no change needed                                                                     |
| `backend/README.md`                         | Not reviewed in depth this pass — flagged for a lighter-touch follow-up read, not blocking this plan                   |

---

## 4. Files to merge (content extracted into new canonical docs, source file then archived or deleted per §5/§6)

| Source file(s)                                                                                                                                                     | Destination                                                                    | What gets kept                                                                                                                 | What gets dropped                                                                                                                                                                                                                                                                                              |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/02-system-architecture.md` (Backend Core Services section, lines ~255-270)                                                                                   | `architecture/01-system-architecture.md`, `architecture/02-workflow-engine.md` | The dynamic-engine service list (`WorkflowDesignerService`, `EngineTransitionService`, etc.) — best explanation in the doc set | The per-role visibility narrative (duplicates docs/00/03/05), the stale "Queue-Based Dashboard Architecture" per-role examples                                                                                                                                                                                 |
| `docs/01-workflow-and-business-rules.md` (SOD guards, claim TTL, terminal-rejection, deprecation notes)                                                            | `architecture/02-workflow-engine.md`                                           | The genuinely current business-rule content (BANK_REJECTED terminal handling, DRAFT_REJECTED_INTERNAL deprecation note)        | The full legacy 18-value status diagram, the entire "Executive Voting Stage" section, "Centralized Workflow Service"/"Voting engine" language                                                                                                                                                                  |
| `docs/03-database-and-models.md` (schema tables + legacy-vs-current disambiguation prose)                                                                          | `architecture/06-database-and-models.md`                                       | ~70% of the file — this is the best-maintained core doc, keep almost entirely                                                  | The reprinted 18-value "Workflow Status Enum" and "Vote Types Enum"/"Voting Session Status Enum" blocks — these actively mislead readers into thinking they're live schema                                                                                                                                     |
| `docs/04-frontend-guide.md` (Per-Role UX Authority deprecation notice, Design Consistency Requirement, corrected routes section, dashboard-family naming)          | `docs/frontend-guide.md` (rewrite)                                             | ~40% — the parts already fixed this session                                                                                    | The full "Voting UI" section (vote-casting buttons, Director controls), "Suggested Navigation by Role" voting nav items, the reprinted status enum                                                                                                                                                             |
| `docs/05-backend-guide.md` (Dynamic Workflow Engine section, Immutable Workflow State Enforcement section, security-rules specifics)                               | `docs/backend-guide.md` (rewrite)                                              | ~30% — the two audit-patched sections plus the rate-limiting/session-fixation specifics                                        | The "2. Voting Service" section entirely, the stale `/api/voting`/`/api/customs`/`/api/support-review`/`/api/workflow` route list (replace with a pointer to `api-reference.md`), the old `support_claimed_by`/`current_status` claim-field references (contradicts docs/03's `claimed_by`/`claim_expires_at`) |
| `docs/00-project-brief.md` (Main Objective/Project Overview framing, ~60 lines)                                                                                    | `docs/README.md` (new top-level index)                                         | The positioning/framing language only                                                                                          | Everything else — it's ~90% duplicate of docs/01/02/03 at lower accuracy                                                                                                                                                                                                                                       |
| `docs/audit-functional/14-dashboard-architecture-decision.md`                                                                                                      | `architecture/04-dashboard-architecture.md`                                    | The two-family model rationale and the actionable-work-invariant explanation                                                   | Decision-record framing (dated, "we decided X because Y") rewritten as present-tense reference ("the system works this way because...")                                                                                                                                                                        |
| `docs/operations/runbook.md` + `docs/operations/retention-policy.md` + `docs/audit-functional/21-audit-closure-report.md` §§12-14 (deployment/rollback checklists) | `docs/production-guide.md` (new)                                               | All of it — no content loss, pure consolidation of 3 files covering deployment/rollback/backup/monitoring into 1               | Nothing dropped, just merged with cross-references removed (since it's now one file, not three pointing at each other)                                                                                                                                                                                         |

---

## 5. Files to archive (moved to `docs/archive/`, kept in full, banner added)

| File(s)                                             | Destination                             | Why archive, not delete                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| --------------------------------------------------- | --------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/00-project-brief.md`                          | `docs/archive/project-brief-2026-05.md` | After extracting the framing language (§4), the rest is a snapshot of pre-engine thinking with genuine historical value (why the project started, original stage-lifecycle intent) — worth keeping as a dated artifact, not worth keeping as a "read this to understand the system" doc                                                                                                                                                                                                                                      |
| `docs/audit-functional/*.md` (all 22 files)         | `docs/archive/audit-functional/`        | This is the full record of the 6-phase RBAC/workflow audit this session performed — every fix has a commit hash, every finding has a test. Deleting it destroys the "why does the code look like this" trail. It is definitively **not** a living reference doc (it's phase-checkpoint genre), so it doesn't belong in the primary docs tree per your stated philosophy, but it has real audit/compliance value for a CBY-regulated financial platform.                                                                      |
| `docs/audit/*.md` (all ~20 files incl. `evidence/`) | `docs/archive/audit-performance/`       | Same reasoning — closed, dated, self-contained performance audit with its own remediation log. Zero overlap with the RBAC/workflow content, so archiving doesn't lose anything the new architecture docs would otherwise contain.                                                                                                                                                                                                                                                                                            |
| `docs/user-view/*.md` (8 files, 6,573 lines)        | `docs/archive/user-view/`               | Already explicitly gated in the Phase F closure report (§11 of `19-phase-f-inventory.md`) — deletion requires separate approval per your own prior instruction (link-fix + destination-agreement + dedicated commit). This plan does not override that gate; it re-confirms it. **UX-pattern content worth mining before archiving** (see the extraction note below), but the files themselves move, not merge, since 6,573 lines of route-by-route/table-by-table spec is too large to hand-merge without a dedicated pass. |

**Extraction note for `docs/user-view/`:** three patterns are genuinely
valuable and don't exist anywhere else in the current doc set — the
"Operational Density Composition" concept (posture tier by role), the
"Forbidden Actions Reference" table pattern (per-role negative-space
documentation), and the "Cross-Role Handoffs" pattern (explicit
producer/consumer relationships between roles at each stage). Recommend
extracting these **three patterns** (not the stale content) into
`docs/frontend-guide.md` as generic, current-architecture-compliant
templates before archiving the source files, so the _pattern_ survives even
though the _specific stale content_ doesn't.

---

## 6. Files to delete (not merged, not archived — genuinely superseded with zero unique value)

**None, on this pass.** Every file reviewed contains either (a) currently
accurate content worth merging, or (b) historical/audit value worth
archiving. The closest candidate for outright deletion is the _specific
sections_ within `docs/00`, `docs/01`, `docs/04`, `docs/05` describing
Executive Voting as live functionality (the "2. Voting Service" section in
`docs/05`, the "Executive Voting Stage" section in `docs/01`, the "Voting
UI" section in `docs/04`) — but these are sub-file sections handled by the
rewrite step in §4, not whole-file deletions. I'm not proposing whole-file
deletion for any of the 35 in-scope files, since even the weakest ones
(`docs/00-project-brief.md`) have salvageable framing language and archival
value once the stale sections are separated out.

**`testing-manual/` needs your explicit decision, not a default here** —
see the open question in §7.

---

## 7. New documents to create

| New file                                         | Purpose                                                                                                                                                                                 | Primary source material                                                                                                                         |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/README.md`                                 | Top-level "start here" index — one paragraph per topic area, links into the new tree, replaces the framing role of `docs/00-project-brief.md`                                           | New writing + docs/00's framing paragraphs                                                                                                      |
| `docs/architecture/01-system-architecture.md`    | High-level system map: Nuxt→Laravel→MySQL→Redis, folder structures, deployment architecture                                                                                             | docs/02 (rewritten, dashboard section replaced)                                                                                                 |
| `docs/architecture/02-workflow-engine.md`        | The Designer, versions, stages, transitions, field rules, permissions, semantic roles/fields, publish lifecycle, runtime execution — **the single canonical workflow-engine reference** | docs/01 (business rules only) + docs/02 (engine services) + docs/decisions/semantic-mapping.md + docs/05 (transition example code)              |
| `docs/architecture/03-permission-model.md`       | DataScope, Stage Permissions, Screen Permissions, active-role resolution, capability model, dynamic roles — currently scattered across 4 files, none of which is "the" permission doc   | docs/02 + docs/03 + docs/05 (auth sections) + AGENTS.md's Core Architecture Rules                                                               |
| `docs/architecture/04-dashboard-architecture.md` | The two-family model as living reference, not decision record                                                                                                                           | docs/audit-functional/14 (rewritten) + AGENTS.md's Dashboard Architecture section                                                               |
| `docs/architecture/05-request-state-model.md`    | The 4-concept canonical state model, why RequestStatus was removed                                                                                                                      | AGENTS.md's Canonical Request State Model section (already the best explanation that exists) + docs/03's legacy-vs-current disambiguation prose |
| `docs/architecture/06-database-and-models.md`    | Schema reference                                                                                                                                                                        | docs/03 (rewritten, stale enum removed)                                                                                                         |
| `docs/engine/extension-guide.md`                 | "How to safely add a new workflow/stage/field/semantic field/semantic role/capability/dashboard metric/effect/screen"                                                                   | New writing, informed by docs/decisions/semantic-mapping.md's consequences section + the Designer service list                                  |
| `docs/engine/dynamic-vs-fixed.md`                | The philosophy doc — what's dynamic (topology, stages, transitions, field rules) vs intentionally fixed (semantic-role enum cases, capability keys)                                     | New writing — this exact distinction doesn't exist anywhere in the current doc set and is a real gap your brief correctly identifies            |
| `docs/development-guide.md`                      | Coding principles, invariants ("never mutate current_status directly," "never hardcode CBY_ADMIN as super-actor"), testing conventions, publish rules                                   | AGENTS.md's "Core Architecture Rules" section (Never Do / Always Do) + backend/CLAUDE.md + frontend/CLAUDE.md's architecture-rules sections     |
| `docs/production-guide.md`                       | Deployment/rollback/backup/environment/security/health-checks/monitoring, single document                                                                                               | operations/runbook.md + operations/retention-policy.md + audit-functional/21's checklists (merge, see §4)                                       |

---

## 8. Cross-reference map (source-of-truth rule)

Every concept gets exactly one authoritative document. Every other mention
becomes a link, not a re-explanation.

| Concept                                                                  | Single source of truth                                                                                                                                             | Files that currently duplicate it (become links after migration)                                                                                                      |
| ------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Workflow stages/transitions/Designer lifecycle                           | `docs/architecture/02-workflow-engine.md`                                                                                                                          | docs/00, docs/01, docs/02, docs/05 (all currently re-explain this)                                                                                                    |
| Request state (runtime_status/current_stage/semantic_role/final_outcome) | `docs/architecture/05-request-state-model.md`                                                                                                                      | AGENTS.md (currently the only accurate copy — becomes a pointer), docs/03, docs/04                                                                                    |
| Permission model (DataScope/StagePermission/ScreenPermission)            | `docs/architecture/03-permission-model.md`                                                                                                                         | docs/02, docs/03, docs/05, AGENTS.md's Core Architecture Rules (partial)                                                                                              |
| Dashboard architecture (two-family model)                                | `docs/architecture/04-dashboard-architecture.md`                                                                                                                   | AGENTS.md (currently the best copy — becomes a pointer), docs/02 (stale copy, replaced), docs/04 (partial, becomes a pointer)                                         |
| Database schema                                                          | `docs/architecture/06-database-and-models.md`                                                                                                                      | docs/00, docs/02 (brief mentions become pointers)                                                                                                                     |
| API routes/contracts                                                     | `docs/api-reference.md`                                                                                                                                            | docs/05's stale route list (deleted, replaced with a pointer)                                                                                                         |
| Per-role visibility rules                                                | `docs/architecture/03-permission-model.md` (as the general mechanism) + `docs/architecture/02-workflow-engine.md` (as stage-permission specifics)                  | docs/00, docs/01, docs/02, docs/03, docs/05 (currently 5 near-duplicate copies)                                                                                       |
| Voting/Executive Voting status                                           | Explicitly stated as OUT OF V1 in `docs/architecture/02-workflow-engine.md` and `docs/development-guide.md`'s "never do" list — one clear statement, not scattered | docs/00, docs/01, docs/04, docs/05, all of docs/user-view/, testing-manual/executive-member.md and committee-director.md (currently describe it as live in 8+ places) |
| Semantic roles/fields mechanism                                          | `docs/architecture/02-workflow-engine.md` (linking to `docs/decisions/semantic-mapping.md` for the dated rationale)                                                | Currently only in the ADR, unreferenced elsewhere — net-new cross-reference, not a de-duplication                                                                     |
| Deployment/rollback/production readiness                                 | `docs/production-guide.md`                                                                                                                                         | operations/runbook.md, operations/retention-policy.md, audit-functional/21 (all merge in, don't survive as separate live docs)                                        |
| Coding invariants ("never hardcode X")                                   | `docs/development-guide.md`                                                                                                                                        | AGENTS.md's Core Architecture Rules (trimmed to a pointer + the rules genuinely specific to AI-tool usage, like SocratiCode workflow)                                 |

---

## 9. Migration plan (incremental, no knowledge lost before execution)

Each step is independently reversible and independently verifiable. Do not
proceed to step N+1 until step N is reviewed and approved — this mirrors
the audit's own phase-gate discipline.

**Step 1 — Create the new tree skeleton (zero content changes).**
Create `docs/architecture/`, `docs/engine/`, `docs/archive/` directories
with placeholder README files stating "content migration in progress, see
`docs/audit-functional/22-documentation-consolidation-plan.md`." No source
file touched. Fully reversible (`rm -rf` the new dirs).

**Step 2 — Write the 5 net-new documents that have no direct source-file
predecessor** (`docs/engine/extension-guide.md`,
`docs/engine/dynamic-vs-fixed.md`, `docs/development-guide.md`,
`docs/README.md`, and `docs/architecture/03-permission-model.md` since it's
assembled from fragments, not a rewrite of one file). These are additive —
nothing is removed from the existing tree yet, so if content turns out
wrong, only the new file needs fixing, no existing doc's meaning changed.

**Step 3 — Rewrite the 3 already-well-patched files in place first**
(`docs/06-api-reference.md` → move to `docs/api-reference.md`,
`docs/03-database-and-models.md` → `docs/architecture/06-database-and-models.md`
with the stale enum block removed, `docs/02-system-architecture.md` →
`docs/architecture/01-system-architecture.md` with the dashboard section
replaced by a pointer to the new dashboard-architecture doc). These are the
lowest-risk rewrites since ~60-70% of each file survives unchanged.

**Step 4 — Rewrite the 3 heavily-stale files**
(`docs/01-workflow-and-business-rules.md` → merges into
`docs/architecture/02-workflow-engine.md`, `docs/04-frontend-guide.md` →
`docs/frontend-guide.md`, `docs/05-backend-guide.md` →
`docs/backend-guide.md`). Higher risk since more content is being deleted
(voting sections) — do these one at a time with a diff review each time,
not as a batch.

**Step 5 — Extract the 3 UX patterns from `docs/user-view/` into
`docs/frontend-guide.md`** (density tiers, forbidden-actions table,
cross-role handoffs pattern) as generic templates — this happens before
archiving so the extraction has the source material still in its original
location for reference during the extraction, not after.

**Step 6 — Merge `docs/00-project-brief.md`'s framing into
`docs/README.md`**, then move the rest of `docs/00` to
`docs/archive/project-brief-2026-05.md`.

\*\*Step 7 — Merge `operations/runbook.md` + `operations/retention-policy.md`

- audit-functional/21's checklists into `docs/production-guide.md`.\*\* Do
  NOT delete the two operations source files in this step — leave them in
  place with a "superseded by docs/production-guide.md" banner for one
  release cycle before removing, in case any external tooling/bookmark
  references their exact path.

**Step 8 — Move `docs/audit-functional/*` and `docs/audit/*` to
`docs/archive/`** verbatim, add the archive-index README explaining what
each subdirectory is and why it's archived rather than deleted. This is a
pure `git mv`, zero content change, fully reversible, and should be its own
dedicated commit per your general "one topic per commit" discipline.

**Step 9 — Rewrite `AGENTS.md`** to point at the new `docs/` tree instead
of duplicating the canonical-state-model/dashboard-architecture content it
currently holds directly. This is the highest-blast-radius single edit
(every AI tool loads this file) — do it last, after every doc it will point
to actually exists at its final path, and re-verify every internal link
resolves before committing.

**Step 10 (gated, separate approval required per your own Phase F
instruction) — decide and execute `docs/user-view/`'s final disposition**
(archive vs. delete) once: no repository links depend on its current path
(verify via `grep -rln "docs/user-view"` returns only archive-pointer
references), the archival destination is confirmed, and the move happens
in its own dedicated commit. This plan proposes `docs/archive/user-view/`
as the destination per §5, but does not execute it.

**Step 11 — `testing-manual/` disposition — open question, not decided by
this plan** (see §10 below). Requires your input before any file in this
directory moves.

Each step above should land as its own commit, following the existing
`docs(scope): description` convention this session already used
throughout the audit — this makes the whole migration `git bisect`-able if
any step turns out to have dropped something important.

---

## 10. Open question requiring your decision

**`testing-manual/`** is structurally a QA artifact (test sequences,
required-user aliases, evidence templates), not an architecture-reference
doc — but it's also the only place a "canonical workflow spine" happy-path
walkthrough exists, and it's still actively useful as a literal test script
if someone is manually verifying the app. Two options:

1. **Treat as QA tooling, keep live at repo root** (`testing-manual/`
   unchanged in location) but flag for a follow-up content-refresh pass
   (fix the legacy status vocabulary, remove `executive-member.md`'s
   voting-centric test, align to the current 4-concept state model) —
   separate from this documentation-consolidation effort, since its
   audience (QA/manual testers) and lifecycle (updated per test cycle, not
   per architecture change) differ from the rest of the docs tree.
2. **Archive alongside `docs/user-view/`** as historical/superseded,
   accepting that a fresh test-plan would need to be written from scratch
   against the current app if manual QA is still practiced.

This plan does not pick one — it's flagged as requiring your explicit
input, consistent with your instruction not to assume every document's
fate.

---

## 11. Missing topics (confirmed gaps, not just staleness)

These concepts exist in code/AGENTS.md but have no dedicated explanation
anywhere in the numbered docs tree, confirmed during the research pass:

1. **Semantic-role/semantic-field mechanism** — only documented in the ADR
   (`docs/decisions/semantic-mapping.md`), never referenced from a core doc.
2. **Dynamic-vs-fixed distinction** — no document currently explains "what
   is configurable through the Designer vs. what requires a code change" —
   your brief's own §6 requirement, genuinely absent today.
3. **Extension guide** — no "how do I safely add a new X" document exists
   anywhere; this knowledge currently lives only in the heads of whoever
   built the Designer service layer.
4. **Compatibility-fallback exit criteria** — documented in AGENTS.md
   (the `SemanticRegistry::stageCodeAliases()` fallback) but not in any
   architecture doc that explains the semantic-role mechanism itself —
   should live alongside the semantic-role explanation, not isolated in
   AGENTS.md's rules list.

---

## 12. What this plan explicitly does not do

- Does not touch `.agents/skills/`, `.claude/skills/`, `.superpowers/`,
  `docs/superpowers/plans+specs/`, `.github/prompts/` — confirmed out of
  scope.
- Does not execute the `docs/user-view/` archival — that remains gated per
  the Phase F closure report's own explicit instruction, re-confirmed here,
  not overridden.
- Does not decide `testing-manual/`'s fate — flagged as an open question.
- Does not delete anything — every file's content has a destination
  (merge target, archive location, or "keep as-is") before this plan is
  presented for approval, per your explicit requirement.

**This plan is ready for your review. No execution will begin until you
approve it (in whole or with modifications) and confirm the answer to §10's
open question.**

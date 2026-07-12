# Documentation Consolidation Plan — Approved Direction (not yet executed)

**Evidence date:** 2026-07-12 · **Status:** Overall direction **APPROVED**.
`testing-manual/` disposition decided (archive, §10). `docs/user-view/`
archival remains gated per the Phase F closure report, unchanged. No file
has been moved, merged, archived, or deleted as part of this document —
execution still requires proceeding through the migration steps in §9,
which remain independently reviewable per step.

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

`docs/audit-functional/` (22 phase-by-phase audit artifacts, files `00`–`21`
— this session's own audit trail; the directory also holds this
consolidation plan itself as a 23rd file, which is not part of that audit
trail and is addressed separately below) and `docs/audit/` (a separate,
closed performance/scalability audit, 32 Markdown files / 50 files total
incl. `evidence/`) are both audit-genre documents — valuable as historical
record, wrong genre for "how the system works today."

---

## 2. Proposed documentation tree

```text
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
│   ├── audit-functional/              MOVE docs/audit-functional/00-21
│   │                                    (22 audit-phase artifacts) here
│   │                                    verbatim — historical audit trail,
│   │                                    valuable, wrong genre for living
│   │                                    docs; this plan (file 22) stays
│   │                                    live until migration completes,
│   │                                    see §9
│   ├── audit-performance/             MOVE docs/audit/* here verbatim
│   │                                    (closed performance audit, same
│   │                                    reasoning)
│   ├── user-view/                     MOVE docs/user-view/* here verbatim,
│   │                                    with a banner file (see §5) — GATED,
│   │                                    per Phase F, execution still pending
│   └── testing-manual/                MOVE testing-manual/* here verbatim,
│                                        with a banner file (see §5) —
│                                        DECIDED: archive (2026-07-12)
├── testing-guide.md                   NEW — future, actively-maintained
│                                        testing guide replacing
│                                        testing-manual/ (see §7)
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

| File(s)                                                                                                                                                                                                | Destination                             | Why archive, not delete                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/00-project-brief.md`                                                                                                                                                                             | `docs/archive/project-brief-2026-05.md` | After extracting the framing language (§4), the rest is a snapshot of pre-engine thinking with genuine historical value (why the project started, original stage-lifecycle intent) — worth keeping as a dated artifact, not worth keeping as a "read this to understand the system" doc                                                                                                                                                                                                                                                                                                                                                                         |
| `docs/audit-functional/{00-discovery.md..21-audit-closure-report.md}` (22 files, i.e. every file EXCEPT `22-documentation-consolidation-plan.md`; see §9 Step 8 for the exact non-glob selection rule) | `docs/archive/audit-functional/`        | This is the full record of the 6-phase RBAC/workflow audit this session performed — every fix has a commit hash, every finding has a test. Deleting it destroys the "why does the code look like this" trail. It is definitively **not** a living reference doc (it's phase-checkpoint genre), so it doesn't belong in the primary docs tree per your stated philosophy, but it has real audit/compliance value for a CBY-regulated financial platform. This plan (`22-documentation-consolidation-plan.md`) stays in place at `docs/audit-functional/` until the migration it describes finishes, rather than archiving alongside the audit it's reporting on. |
| `docs/audit/*` (32 Markdown files, 50 files total incl. `evidence/`)                                                                                                                                   | `docs/archive/audit-performance/`       | Same reasoning — closed, dated, self-contained performance audit with its own remediation log. Zero overlap with the RBAC/workflow content, so archiving doesn't lose anything the new architecture docs would otherwise contain.                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `docs/user-view/*.md` (8 files, 6,573 lines)                                                                                                                                                           | `docs/archive/user-view/`               | Already explicitly gated in the Phase F closure report (§11 of `19-phase-f-inventory.md`) — deletion requires separate approval per your own prior instruction (link-fix + destination-agreement + dedicated commit). This plan does not override that gate; it re-confirms it. **UX-pattern content worth mining before archiving** (see the extraction note below), but the files themselves move, not merge, since 6,573 lines of route-by-route/table-by-table spec is too large to hand-merge without a dedicated pass.                                                                                                                                    |
| `testing-manual/*.md` (9 files, 813 lines)                                                                                                                                                             | `docs/archive/testing-manual/`          | **Decided 2026-07-12.** Predates the dynamic workflow engine (legacy status vocabulary throughout, `executive-member.md`/`committee-director.md` test a removed voting feature) and must not remain a live reference the QA team could mistake for current guidance, even though it's partially ahead of `docs/user-view/` on routes (`/workflows`, not `/requests`). Archived in full, not deleted — same reasoning as `docs/user-view/`: real historical/QA value, wrong genre for a live reference. Replaced going forward by `docs/testing-guide.md` (§7), a new actively-maintained document, not a refresh of the archived files.                         |

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

**`testing-manual/`** is archived, not deleted — see §5 and the decision
record in §10.

---

## 7. New documents to create

| New file                                         | Purpose                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | Primary source material                                                                                                                                                                                                                                                                                                         |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/README.md`                                 | Top-level "start here" index — one paragraph per topic area, links into the new tree, replaces the framing role of `docs/00-project-brief.md`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | New writing + docs/00's framing paragraphs                                                                                                                                                                                                                                                                                      |
| `docs/architecture/01-system-architecture.md`    | High-level system map: Nuxt→Laravel→MySQL→Redis, folder structures, deployment architecture                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | docs/02 (rewritten, dashboard section replaced)                                                                                                                                                                                                                                                                                 |
| `docs/architecture/02-workflow-engine.md`        | The Designer, versions, stages, transitions, field rules, permissions, semantic roles/fields, publish lifecycle, runtime execution — **the single canonical workflow-engine reference**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | docs/01 (business rules only) + docs/02 (engine services) + docs/decisions/semantic-mapping.md + docs/05 (transition example code)                                                                                                                                                                                              |
| `docs/architecture/03-permission-model.md`       | DataScope, Stage Permissions, Screen Permissions, active-role resolution, capability model, dynamic roles — currently scattered across 4 files, none of which is "the" permission doc                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | docs/02 + docs/03 + docs/05 (auth sections) + AGENTS.md's Core Architecture Rules                                                                                                                                                                                                                                               |
| `docs/architecture/04-dashboard-architecture.md` | The two-family model as living reference, not decision record                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | docs/audit-functional/14 (rewritten) + AGENTS.md's Dashboard Architecture section                                                                                                                                                                                                                                               |
| `docs/architecture/05-request-state-model.md`    | The 4-concept canonical state model, why RequestStatus was removed                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | AGENTS.md's Canonical Request State Model section (already the best explanation that exists) + docs/03's legacy-vs-current disambiguation prose                                                                                                                                                                                 |
| `docs/architecture/06-database-and-models.md`    | Schema reference                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | docs/03 (rewritten, stale enum removed)                                                                                                                                                                                                                                                                                         |
| `docs/engine/extension-guide.md`                 | "How to safely add a new workflow/stage/field/semantic field/semantic role/capability/dashboard metric/effect/screen"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | New writing, informed by docs/decisions/semantic-mapping.md's consequences section + the Designer service list                                                                                                                                                                                                                  |
| `docs/engine/dynamic-vs-fixed.md`                | The philosophy doc — what's dynamic (topology, stages, transitions, field rules) vs intentionally fixed (semantic-role enum cases, capability keys)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | New writing — this exact distinction doesn't exist anywhere in the current doc set and is a real gap your brief correctly identifies                                                                                                                                                                                            |
| `docs/development-guide.md`                      | Coding principles, invariants ("never mutate current_status directly," "never hardcode CBY_ADMIN as super-actor"), testing conventions, publish rules                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | AGENTS.md's "Core Architecture Rules" section (Never Do / Always Do) + backend/CLAUDE.md + frontend/CLAUDE.md's architecture-rules sections                                                                                                                                                                                     |
| `docs/production-guide.md`                       | Deployment/rollback/backup/environment/security/health-checks/monitoring, single document                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | operations/runbook.md + operations/retention-policy.md + audit-functional/21's checklists (merge, see §4)                                                                                                                                                                                                                       |
| `docs/testing-guide.md`                          | **Future, actively-maintained testing guide** replacing `testing-manual/` (archived, §5/§10) — not a refresh of the archived content, a fresh document aligned to the current architecture. Required coverage, per explicit instruction: `runtime_status`, `current_stage`, `semantic_role`, `final_outcome` (how to construct/verify test scenarios for each state combination); dynamic workflow paths (testing against Designer-defined stages/transitions rather than a fixed step list, since the topology itself is data, not code); capability-based permissions (screen/stage capability test matrix, not a fixed 8-role table); organization and data-scope enforcement (cross-org/cross-bank isolation test patterns, the same DataScope mechanism `architecture/03-permission-model.md` documents). Structurally should reuse `testing-manual/`'s good bones (required-test-user-aliases table, evidence template, exit criteria) since those patterns aren't architecture-coupled and don't need to change — only the state/workflow/permission content needs to be rewritten from scratch against the current model. | New writing, informed by `architecture/02-workflow-engine.md`, `architecture/03-permission-model.md`, `architecture/05-request-state-model.md` (once those exist) + the archived `testing-manual/`'s structural patterns (test-user-alias table, evidence template, exit criteria — reusable scaffolding, not reusable content) |

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
| Manual/QA testing procedure                                              | `docs/testing-guide.md`                                                                                                                                            | `testing-manual/` (archived in full, not a live pointer target — the new guide is a fresh document, not a rewrite-in-place)                                           |

---

## 9. Migration plan (incremental, no knowledge lost before execution)

Each step is independently reversible and independently verifiable. Do not
proceed to step N+1 until step N is reviewed and approved — this mirrors
the audit's own phase-gate discipline.

**Step 1 — Create the new tree skeleton (zero content changes). ✅ DONE
(2026-07-12).** Created `docs/architecture/README.md`,
`docs/engine/README.md`, `docs/archive/README.md` — each a placeholder
stating migration is in progress, linking back to this plan, and (for
`docs/architecture/` and `docs/engine/`) pointing at the still-authoritative
existing docs in the meantime. `docs/archive/README.md` additionally
previews its planned contents and re-states the `docs/user-view/` archival
gate. The three README files were added; the only existing tracked file
touched was this consolidation plan itself, modified solely to record Step
1's completion and apply the disclosed Markdown-lint fix (a language tag on
the tree diagram's fenced code block) — no other existing tracked file was
modified, confirmed via `git status`. All 3 Markdown links in the new files
verified to resolve. Prettier clean. Fully reversible (`rm -rf` the 3 new
directories, `git checkout` this file). No other deviation from the plan as
written.

**Step 2 — Write the 5 net-new documents that have no direct source-file
predecessor. ✅ DONE (2026-07-12).** (`docs/engine/extension-guide.md`,
`docs/engine/dynamic-vs-fixed.md`, `docs/development-guide.md`,
`docs/README.md`, and `docs/architecture/03-permission-model.md` since it's
assembled from fragments, not a rewrite of one file). These are additive —
nothing is removed from the existing tree yet, so if content turns out
wrong, only the new file needs fixing, no existing doc's meaning changed.

Before writing, two SocratiCode codebase-explorer agents independently
verified the permission-model and workflow-engine-extension claims against
the actual backend source (not against legacy docs). This surfaced three
corrections applied to the new docs rather than inherited from stale
assumptions: (1) the live Support claim TTL is read from
`AdminSettingsService`'s `support_claim_ttl` setting via
`EngineClaimService::ttlMinutes()`, not from
`config('workflow.support_claim_ttl_minutes')` — the config key exists but
is unused at runtime; both default to 15 minutes today, so this had not
surfaced as a behavioral bug, only a documentation-accuracy one; (2)
AGENTS.md's "CBY_ADMIN must never act as a workflow super-actor" rule is
enforced in code only for the single `merchants:MANAGE` screen capability
(`PermissionService::userHasCapability()`) — the broader claim about
Director/SWIFT/Support/Bank Reviewer/Executive stages is a seeding
convention with no structural guard in `StagePermissionConsistency` or
`StagePermissionPolicy`, documented as such rather than as a code-enforced
invariant; (3) the runtime-status class is `App\Support\EngineRequestStatus`
(a plain class with string constants), not `App\Enums\EngineRequestStatus`
as AGENTS.md's phrasing implies. All three are noted explicitly in the new
docs so they read as verified fact, not inherited assumption.

Every internal link across all 5 files was extracted and checked against
the filesystem; two classes of link bugs were found and fixed before
commit: (a) `docs/README.md` initially linked to the existing numbered
docs as `../0X-*.md`, which resolves outside `docs/` from a file that
lives inside `docs/` — corrected to bare `0X-*.md` sibling links; (b)
several links to the Step 3/4 not-yet-written architecture docs
(`architecture/02-workflow-engine.md`, `architecture/04-dashboard-architecture.md`,
`architecture/05-request-state-model.md`) were originally rendered as live
Markdown links that would 404 until Steps 3–4 land — rewritten as plain
code-span paths with an explicit "**planned, not yet written** — Step N;
today's authority is X" annotation, so nothing in the new docs looks live
when it isn't. Prettier's auto-reflow twice turned a line starting with a
list-like character (`+`, then later a lone `-`) into a spurious bullet
that duplicated or stranded adjacent text — caught both times by reading
the file back after `--write` and confirming a second `--write` pass
reported the file unchanged before moving on.

Spot-verified a sample of the sub-agents' most load-bearing claims
directly against source after drafting (not just trusting the agent
reports): `UserRole` enum's 8 cases, `StageAccessLevel` (`VIEW`/`EXECUTE`
only), `ScreenCapability` (`VIEW`/`MANAGE`/`EXPORT`), the claim-TTL
divergence, the `merchants:MANAGE` guard's exact condition, and
`EngineTransitionService::execute()`'s permission-before-claim check
ordering — all matched the docs as written. Confirmed via `git status`
that only the 5 new files were added; the 2 pre-existing dirty files and
11 pre-existing untracked files from before Step 2 began are unchanged.
Prettier clean on all 5 files. No deviation from the plan as written.

**Step 2 accuracy correction (2026-07-12).** A focused review caught 9
overstated or imprecise claims across 4 of the 5 files (not
`docs/README.md`) that the initial verification pass missed because it
checked specific facts rather than every generalization built on top of
them. All 9 were corrected directly against source, re-verified, and
re-committed as a documentation-only follow-up (no production code
touched):

1. `architecture/03-permission-model.md` — removed "both systems key off
   `roles.id`"; screen permissions are role-keyed, stage permissions match
   an identity set (org/team/role/user, each optional).
2. Same file — `UserRole` is not merely "for API serialization only"; it's
   also used by `User::asUserRole()`/`hasRole()`/`isBankRole()`/
   `isCbyRole()`, two query scopes, and
   `Notifications\NotificationRegistry`'s recipient selection. It's simply
   not consulted by the two permission _resolvers_.
3. Same file — removed the blanket "every mutating service logs inside
   the same DB transaction" claim. `AuditService::log()` is always a
   manual, explicit call, but the transaction boundary is caller-defined:
   `EngineTransitionService::execute()` does wrap it, but
   `PasswordRecoveryService` calls it after a bare `$user->save()` with no
   surrounding transaction.
4. `engine/dynamic-vs-fixed.md` — removed "enforce DAG-ness." The
   publish validator does not reject cycles; it only requires an
   explicit `is_self_loop` flag when `from_stage_id === to_stage_id`.
   Correction/return loops back to an earlier stage are intentional and
   unrestricted.
5. `engine/extension-guide.md` — clarified that every effect failure
   rolls back the transition, but only an unexpected `\Throwable` gets
   wrapped as `STAGE_HOOK_FAILED`/422; a domain exception
   (`EngineException`, `FinancingLimitExceededException`,
   `FinancingLockTimeoutException`, `CustomsException`) propagates with
   its own error envelope intact.
6. Same file — split "add a dashboard metric" by family: operational
   metrics go through `DashboardWorkController`/`GET /api/dashboard/work`
   (its `actionable` section backed directly by
   `UserActionableRequestQuery`); analytics/governance metrics go through
   `DashboardStatsService`/`GET /api/dashboard/stats`. The original text
   blurred these into one pipeline.
7. `docs/development-guide.md` — corrected "Vue 4" to "Vue 3.5"
   (`frontend/package.json` pins `^3.5.13`) and removed "claim TTL" from
   the Redis cell — claim expiry is `engine_requests.claim_expires_at`
   (MySQL), not Redis.
8. Same file — replaced the nonexistent `current_status` field with the
   real persistence fields (`EngineRequest`'s `status`,
   `current_stage_id`), and distinguished them from the API-facing names
   `EngineRequestResource` maps them to (`runtime_status`,
   `current_stage`).
9. Normalized bare endpoint examples (`auth/me`,
   `roles/{role}/screen-permissions`, `workflow-versions/{v}/validate`,
   `workflow-versions/{v}/publish`) to their actual registered paths —
   `GET /api/auth/me`, `PUT /api/v1/roles/{role}/screen-permissions`,
   `POST /api/v1/workflow-versions/{workflowVersion}/validate`,
   `POST /api/v1/workflow-versions/{workflowVersion}/publish` — confirmed
   via `php artisan route:list`.

**Item 10 was investigated and found to have no discrepancy to record.**
The correction request's premise was that the backend registers
`/api/v1/dashboard/work` while the frontend and AGENTS.md use
`/api/dashboard/work`. `php artisan route:list --path=dashboard/work`
shows exactly one registration:
`GET|HEAD api/dashboard/work → Api\V1\DashboardWorkController@work`, sitting
in an **unprefixed** `Route::middleware([...])->group()` block in
`backend/routes/api.php` — not inside the adjacent `Route::prefix('v1')`
group, despite the controller class living in the `Api\V1` namespace.
There is no `v1`-prefixed registration of this route anywhere in
`routes/api.php`, and `frontend/app/composables/useDashboardWork.ts`
already calls `/api/dashboard/work`, matching AGENTS.md. Flagged this
finding back before acting on it; confirmed with the requester to skip
adding a false follow-up entry rather than record a mismatch that
`route:list` does not show. No plan or code change results from item 10.

Re-ran Prettier on all 4 corrected files (`03-permission-model.md`,
`extension-guide.md`, `dynamic-vs-fixed.md`, `development-guide.md`) and
re-validated every internal link in all 5 Step 2 files against the
filesystem — all resolve or are annotated planned, unchanged from the
original Step 2 pass. `docs/README.md` needed no changes for this
correction. Confirmed via `git status` that only these 4 files plus the
plan doc are dirty; the 2 pre-existing dirty files and 11 pre-existing
untracked files remain unchanged.

**Step 2 accuracy correction, round 2 (2026-07-12).** A further narrow
review caught 4 more precision errors in 2 of the already-corrected
files, verified directly against source before editing:

1. `architecture/03-permission-model.md` — corrected classification
   method ownership, which had model and enum reversed: it's
   `App\Models\User::isBankUser()`/`isCbyUser()` (not `isBankRole()`/
   `isCbyRole()` on the model), which delegate to
   `App\Enums\UserRole::isBankRole()`/`isCbyRole()` on the enum itself
   (not the model).
2. Same file — removed the remaining "every mutating service makes an
   explicit `AuditService::log()` call" framing. Confirmed a real
   counterexample: `App\Services\Settings\UserPreferencesService` mutates
   `user_preferences` via `updateForUser()`/`resetForUser()`/
   `saveSection()` and calls `$user->save()` directly with **no
   `AuditService` dependency at all** — not merely a caller that skips the
   call, but a service that was never wired to make it. Rewrote to state
   coverage must be verified per caller, not assumed from the pattern
   being common elsewhere.
3. `engine/extension-guide.md` — stopped writing
   `EngineException('STAGE_HOOK_FAILED', 422)` as if that were the real
   call. `App\Exceptions\EngineException::__construct()` is
   `(string $message, string $errorCode, int $httpStatus = 422, array $errors = [])`
   — the wrapped-throwable path constructs one with a real message plus
   `errorCode: 'STAGE_HOOK_FAILED'`, `httpStatus: 422`; documented the
   full shape instead of an inaccurate two-arg shorthand.
4. Same file — corrected the "Analytics/governance metrics" section's
   characterization of `DashboardStatsService`. Its `match(true)` in
   `stats()` is not analytics-only: alongside the two analytics branches
   (`cbyadminStats()`, `bankAdminStats()`), it still contains six legacy
   workflow-role branches (`dataEntryStats()`, `bankReviewerStats()`,
   `supportCommitteeStats()`, `swiftOfficerStats()`,
   `executiveMemberStats()`, `committeeDirectorStats()`) for
   `INTAKE`/`INTERNAL_REVIEWER`/`SUPPORT`/`FX_SWIFT`/
   `COMMITTEE_MANAGER`/`COMMITTEE_DIRECTOR`. Rewrote to direct new
   operational metrics to `DashboardWorkController` exclusively and new
   analytics metrics to the two analytics branches only, explicitly
   naming the six legacy branches as off-limits for extension rather than
   describing the service as if it only served analytics roles.

Re-ran Prettier on both corrected files (unchanged on first pass, no
reformatting needed) and re-validated every internal link across all 5
Step 2 files against the filesystem — all resolve or are annotated
planned. Confirmed via `git status` that only `03-permission-model.md`,
`extension-guide.md`, and this plan doc are dirty from this round; the 2
pre-existing dirty files and 11 pre-existing untracked files remain
unchanged.

**Step 3 — split into two independently reviewed substeps (clarified
2026-07-12).** `docs/README.md`'s Step 2 table already assigns 5
documents to "Step 3" — `architecture/01-system-architecture.md`,
`architecture/04-dashboard-architecture.md`,
`architecture/05-request-state-model.md`,
`architecture/06-database-and-models.md`, and `api-reference.md` — but
the migration sequence as originally written only listed 3 of them,
silently omitting `04-dashboard-architecture.md` and
`05-request-state-model.md`. Splitting corrects that gap and keeps each
substep separately reviewable rather than bundling 5 documents of
differing risk into one step:

**Step 3A — rewrite/move the 3 already-well-patched files with a direct
source predecessor:**

- `docs/06-api-reference.md` → `docs/api-reference.md` (move, minimal
  content change, routes re-verified against `php artisan route:list`).
- `docs/03-database-and-models.md` →
  `docs/architecture/06-database-and-models.md` (rewrite, stale
  status-enum and voting material removed, schema re-verified against
  migrations/models).
- `docs/02-system-architecture.md` →
  `docs/architecture/01-system-architecture.md` (rewrite, the stale
  fixed-per-role dashboard section replaced with an annotated pointer to
  Step 3B's not-yet-written dashboard-architecture doc).

These are the lowest-risk rewrites since ~60-70% of each file survives
unchanged, and each already has a direct 1:1 source predecessor.

**Step 3B — write the 2 documents `docs/README.md` assigns to Step 3
but that have no direct source-file predecessor:**

- `docs/architecture/04-dashboard-architecture.md` — promotes
  `audit-functional/14`'s decision-record content to canonical reference
  genre (per §2's tree note), explains why it replaced fixed per-role
  dashboards, explains the shared actionable-work invariant.
- `docs/architecture/05-request-state-model.md` — the 4-concept model
  (`runtime_status`/`current_stage`/`semantic_role`/`final_outcome`),
  merging `docs/03`'s schema notes with AGENTS.md's canonical-model
  section, explaining why the old `RequestStatus` enum no longer exists.

Both are assembled from fragments (an audit checkpoint, scattered
AGENTS.md sections) rather than a single source file being renamed, so
they're closer in kind to Step 2's net-new documents than to 3A's
rewrites — hence the separate substep and separate review gate.

**Step 3A — ✅ DONE (2026-07-12).** Before touching any source document,
used a `socraticode:codebase-explorer` subagent (resumed once after an
API-error truncation) plus direct verification of the two claims it
flagged as unresolved, to check every retained schema/route/behavioral
claim against current source — not carried over from the prior version of
any file.

**`docs/06-api-reference.md` → `docs/api-reference.md`** (moved via `git
mv` to preserve history). Cross-checked against `php artisan
route:list --path=api`, run locally on 2026-07-12 — the route count is
**not** recorded as a fixed number here, since demo/switch-role routes
register conditionally on `config('demo.allowed_environments')` and the
total varies by environment. Findings: the documented endpoints were
individually accurate, but the document covers only a fraction of the
real API surface — entire route families are undocumented (Workflow
Designer admin CRUD, reference data admin, org/team/role/screen admin,
merchants, governance/compliance, profile/MFA/sessions, search, most
`ReportController` analytics
endpoints, plus smaller gaps like `available-workflows`, `.../abandon`,
`.../documents/{document}/replace`, and several `AuthController` routes).
Per your direction (see below), did not expand Step 3A into a full
rewrite to close this gap — instead added a prominent "Coverage status"
section stating the verification date/method, listing every undocumented
route family by name, and directing readers to `route:list` and the
controllers as the temporary authority for anything not yet covered.
Corrected two real inaccuracies while moving: the claim-TTL reference
(`config('workflow.support_claim_ttl_minutes')` is unused; the live value
is `AdminSettingsService`'s `support_claim_ttl`, same finding as the
Step 2 corrections), and removed the entire "Voting"/"Allowed
Votes"/"Voting Rules" sections plus scattered voting mentions
("Voting statistics" dashboard claim, "Executive queues are
voting-scoped," two "voting open/close" transition examples) — confirmed
via `grep` across `backend/app` that no `EXECUTIVE_VOTING_OPEN`,
`AUTO_ABSTAIN_TIMEOUT`, or vote-type enum exists anywhere in the
codebase; Executive Voting is out of V1 per AGENTS.md, and this
functionality was never merely deprecated in this doc but described as
if live. **Correction (2026-07-12, see the follow-up correction round
below):** this original grep was too narrow — it disproved a live V1
voting _feature_ (routes, session model, active vote data) but did not
disprove all voting-related symbols in `backend/app`. Legacy compatibility
symbols remain (`NotificationType::VOTING_OPENED`, `AuditAction::VOTE_CAST`,
`App\DTOs\Voting\VotingTally`, `VotingTallyResource`), unreachable from any
live route but present in the codebase — see `docs/api-reference.md`'s
"Executive Voting (out of V1 — no live routes)" section, which now
documents this distinction precisely instead of claiming zero. Kept the
one general (non-voting-specific) concurrency-locking
paragraph, retitled from "Voting Concurrency Protection" to "Transition
Concurrency Protection," since the underlying pessimistic-locking
mechanism it describes is real and applies to every transition, not a
voting-specific one.

**Missing API-family documentation is a defined follow-up, not a silent
deferral.** Tracked here in this plan (this entry) and referenced from
`docs/api-reference.md`'s own Coverage status section: `docs/api-reference.md`
must not be treated as the complete canonical API reference until a
future step documents the Workflow Designer admin API, reference data
admin, org/team/role/screen admin, merchants, governance/compliance
endpoints, `ReportController`'s analytics family, profile/MFA/session
management, search, and the smaller per-family gaps listed above. This
follow-up needs its own step number when scheduled — it is out of scope
for the current 12-step sequence as written and should be added
explicitly before Steps 3B–12 are considered to fully close out API
documentation.

**`docs/03-database-and-models.md` → `docs/architecture/06-database-and-models.md`**
(rewrite; old file removed via `git rm` since content changed
substantially, not a straight move). Verified against
`backend/database/migrations/` and `backend/app/Models/` directly.
Findings went well beyond the originally-scoped "remove the stale status
enum and voting material": confirmed real schema drift on nearly every
table — `workflow_stages` was missing `semantic_role`, `attached_effects`,
and `final_outcome` columns (all added by later migrations); `engine_requests`
was missing `claim_stage_id`, `stage_entered_at`, `sla_deadline_epoch`,
`invoice_number_normalized`, and several composite indexes; `workflow_history`
was missing `correlation_id` (the UUID shared with paired `audit_logs`
rows) and its `to_stage_id` nullable change; `audit_logs` was missing
`bank_id`; `customs_declarations` was missing `metadata`,
`signed_fx_doc_*`, `generated_by`, `signed_uploaded_by`, and its
`issued_by`-now-nullable change, plus its `request_id` column has been
fully dropped (not merely deprecated) by the same migration that drops
the legacy tables; and a wholly undocumented new table,
`engine_request_reference_sequences`, exists. Also confirmed the
`engine_requests.status` column is a plain `string(20)`, not a
database-level enum — the 5-value `ACTIVE`/`CLOSED`/`REJECTED`/
`CANCELLED`/`ABANDONED` constraint (matching AGENTS.md's canonical
`runtime_status`, not the old doc's undocumented-as-incomplete 3-value
claim) is application-level only, via `App\Support\EngineRequestStatus`.
Removed the "Voting Rules"/"Vote Types Enum"/"Voting Session Status Enum"
sections and the 18-value pre-engine "Workflow Status Enum" entirely
(rather than annotating them as historical, since AGENTS.md is explicit
that this vocabulary was replaced, not merely supplemented, by the
4-concept request-state model) — confirmed via the same drop-migration
(`2026_07_01_000001_p5_drop_legacy_import_request_tables.php`) that
`request_votes` was physically dropped alongside `import_requests`,
`request_documents`, and `request_stage_history`.

**`docs/02-system-architecture.md` → `docs/architecture/01-system-architecture.md`**
(rewrite; old file removed via `git rm`). Corrected "Vue 4" to "Vue 3.5"
(same finding as Step 2); replaced the large fixed-per-role "Queue-Based
Dashboard Architecture" and "Visibility Model" sections (per-role
dashboard enumeration including an Executive voting queue) with an
annotated pointer to `04-dashboard-architecture.md` (**planned, Step
3B**) and a short note that the fixed-per-role model is superseded by the
two-family model, rather than silently deleting the content with no
successor pointer. Verified the backend/frontend service class names and
folder structures directly (`ls backend/app`, `ls frontend/app`) — both
had drifted from what the doc listed (backend: doc was missing
`Console/`, `Exceptions/`, `Mail/`, `OpenApi/`, `Providers/`, `Rules/`,
and claimed `Actions/`/`Events/`/`Notifications/` that don't currently
exist as top-level directories; frontend: doc claimed a `services/`
folder that doesn't exist and was missing `lib/`, `schemas/`, `tests/`).
Confirmed all 7 named `Services/Workflow/` classes
(`WorkflowDesignerService`, `WorkflowVersionValidator`,
`StagePermissionResolver`, `EngineTransitionService`,
`EngineClaimService`, `StageFieldRuleValidator`, `RequestProjectionSync`)
and `AuditService`'s location still match.

**Link updates.** Found and fixed every in-scope live reference to the 3
old paths: `AGENTS.md` (2 spots — the doc-authority list and the file
downloads bullet), `frontend/CLAUDE.md`, `backend/CLAUDE.md`, this plan's
own §2 tree/§5 table entries were already forward-looking and needed no
change, `docs/README.md` (3 table rows flipped from "planned" to
"live"), `docs/architecture/README.md` (rewritten — it previously
claimed `docs/00`–`docs/07` as the fallback authority, which was now
false for 2 of those paths), `docs/engine/README.md` (rewritten — it
still described the extension guide/dynamic-vs-fixed docs as
not-yet-written, though Step 2 already shipped them, and referenced the
now-deleted `docs/02-system-architecture.md`), and `docs/04-frontend-guide.md`
(one dead link to `docs/03-database-and-models.md`, fixed to point at the
new path with a note that the section itself is stale pre-engine material
scheduled for Step 4's rewrite — not rewritten now, since that's
out-of-scope for 3A). `docs/superpowers/plans/*.md` references were left
untouched — AI-tooling scaffolding, out of scope per the original scoping
decision.

**Verification.** Prettier run on all 10 touched files, clean and stable
on the first pass for most; one file (`docs/04-frontend-guide.md`) needed
a second Prettier pass whose diff was reviewed and confirmed to contain
only table/list reflow, no content change. Every internal link across all
14 files touched by Steps 2+3A was extracted and checked against the
filesystem — found and fixed one live-looking link in the new database
doc pointing at the not-yet-written `04-dashboard-architecture.md`
(rewrote to the same plain-code-span "planned" annotation pattern used
elsewhere), then re-verified zero broken links remain. Confirmed via
`git status` that the working tree's dirty/untracked set matches exactly
what Step 3A was expected to touch, plus the 2 pre-existing dirty files
and 11 pre-existing untracked files from before this session began,
unchanged throughout.

**Deviations from the plan as written**, all disclosed above rather than
silently absorbed: (1) `docs/03-database-and-models.md`'s rewrite went
well beyond "remove the stale status enum and voting material" once
schema verification surfaced broader drift — every table needed at least
minor correction; (2) `docs/06-api-reference.md`'s move surfaced a large
undocumented-route-family gap that was explicitly scoped out of 3A per
your direction and instead recorded as a defined, owned follow-up (not
yet assigned a step number) rather than silently left as an implicit
"someday"; (3) `docs/architecture/README.md` and `docs/engine/README.md`
needed more than link fixes — their narrative content was stale relative
to Step 2's already-shipped files and 3A's newly-live files, so both were
substantively rewritten, not just patched.

**Step 3A accuracy correction (2026-07-12).** A further review caught 9
overstated or imprecise claims, spanning 3 of the Step 3A files, verified
directly against source before editing:

1. `docs/api-reference.md` — removed the hardcoded "237 registered
   routes" count; replaced with the verification command, environment,
   and date, plus an explicit note that demo/switch-role routes register
   conditionally on `config('demo.allowed_environments')` so the total
   varies by environment. Applied the same fix to this plan's own Step
   3A record above.
2. Same file — corrected the voting-removal wording from an implied
   "zero voting code anywhere in `backend/app`" to the precise claim: no
   live V1 workflow routes, vote-casting service, session model, or
   active vote data model, **but** legacy compatibility/dead-code symbols
   remain (`NotificationType::VOTING_OPENED`, `AuditAction::VOTE_CAST`,
   voting notification templates, `App\DTOs\Voting\VotingTally`,
   `VotingTallyResource`, the zeroed dashboard-stats voting fields).
   Recorded these as cleanup debt in a new "Executive Voting (out of V1
   — no live routes)" section — no production code touched.
3. `docs/architecture/06-database-and-models.md` — reconstructed
   `users`, `banks`, `organizations`/`teams`/`roles`, and the
   `user_roles`/`user_teams` pivots from every applicable migration (not
   just the base create), not the assumed shape from the prior doc
   version. Confirmed `users.role` is fully dropped
   (`2026_07_07_000001_drop_users_role_column.php`), added
   `organizations.classification` (the field `DataScope::forUser()`
   reads), added `version`/optimistic-concurrency columns across
   `banks`/`organizations`/`teams`/`roles`, added `user_roles.is_active`
   (confirmed present) and confirmed `user_teams` has **no** equivalent
   column, added the 4 missing `workflow_transitions` fields
   (`is_default_submit`, `is_self_loop`, `transition_type`,
   `is_destructive`, all from
   `2026_07_06_000004_wp3_designer_validation_columns.php`), and added
   the previously-undocumented `field_groups` and `stage_field_rules`
   tables plus a fuller `field_definitions` column list (it had ~9
   columns documented vs. ~24 actual).
4. Same file — added an explicit "Coverage status" section stating this
   is a core-workflow schema reference, not an exhaustive database
   catalog, and listing the omitted table families (reference data,
   merchants, notifications, report-exports, archive tables, screens —
   the last already covered in the permission-model doc instead).
   Updated `docs/architecture/01-system-architecture.md` to stop calling
   it "the full schema."
5. Same 2 files — corrected `engine_request_documents`: added the
   missing `status` column (`DocumentStatus` enum: `active`\|`superseded`)
   and replaced "immutable uploads (SWIFT documents cannot be replaced)"
   with an accurate description of controlled versioned replacement via
   `EngineRequestDocumentReplacementService`, confirmed
   `DOCUMENT_LOCKED` gates deletion only (by stage), not replacement, and
   confirmed no document-type exclusion exists for SWIFT specifically.
   Added a "Replace Request Document" section to `docs/api-reference.md`
   documenting the endpoint, which had never been documented at all.
6. `docs/architecture/06-database-and-models.md`,
   `docs/architecture/01-system-architecture.md`, and
   `docs/api-reference.md` — corrected request visibility in all three:
   confirmed via `EngineRequest::scopeForUser()` and
   `EngineRequestController::index()` that visibility requires **both**
   `DataScope` (organization/bank) **and** stage VIEW permission
   (`StagePermissionResolver::accessibleStageIds()`) for any
   non-`system_admin` user — replaced every "all users inside the same
   bank can view all bank requests" / "Data Entry users can view all bank
   requests" claim with the precise two-dimension composition.
7. `docs/architecture/01-system-architecture.md` — corrected "current
   stage expresses business status — not a separate status column" (which
   implied there's no separate status column) to state plainly that
   `engine_requests` has **two** separate columns: `status` (the 5-value
   runtime lifecycle) and `current_stage_id` (the fine-grained business
   position), neither substituting for the other.
8. Added **Step 13 — Complete API Reference Coverage** to this plan
   (below, after Step 12), with the exact route families already listed
   in `docs/api-reference.md`'s Coverage status section as its acceptance
   scope — the missing-API-documentation follow-up now has an assigned
   step rather than "needs a step number when scheduled."
9. `docs/api-reference.md` — repaired the Coverage status section's
   malformed multiline inline-code (endpoint examples that had been
   split mid-backtick across lines by an earlier automated reflow),
   restoring every example to one complete, correctly-rendered endpoint.

Re-ran Prettier on the 4 touched files (`api-reference.md`,
`architecture/06-database-and-models.md`,
`architecture/01-system-architecture.md`, this plan doc) — clean and
stable. Re-validated every internal link across all 15 files touched by
Steps 2+3A+this correction round against the filesystem — all resolve or
are annotated planned. Re-ran `php artisan route:list --path=api`
(237 routes in the local environment at correction time, not recorded as
a claim in the docs themselves per item 1). Confirmed via `git status`
that only the 4 corrected files plus this record are dirty; the 2
pre-existing dirty files and 11 pre-existing untracked files remain
unchanged.

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

**Step 7 — Merge `operations/runbook.md`, `operations/retention-policy.md`,
and `audit-functional/21`'s checklists into `docs/production-guide.md`.**
Do NOT delete the two operations source files in this step — leave them in
place with a "superseded by docs/production-guide.md" banner for one
release cycle before removing, in case any external tooling/bookmark
references their exact path.

**Step 8 — Move `docs/audit-functional/00-discovery.md` through
`docs/audit-functional/21-audit-closure-report.md`** (every file in the
directory EXCEPT `22-documentation-consolidation-plan.md`, which stays
live — do not use `docs/audit-functional/*`, since that glob would also
sweep up this plan) **and all of `docs/audit/*` to `docs/archive/`**
verbatim, add the archive-index README explaining what each subdirectory is
and why it's archived rather than deleted. This is a pure `git mv`, zero
content change, fully reversible, and should be its own dedicated commit
per your general "one topic per commit" discipline.

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

**Step 11 — Move `testing-manual/*` to `docs/archive/testing-manual/`**
verbatim, add a banner file explaining the archival reason (predates the
dynamic engine, replaced by `docs/testing-guide.md`), same pure-`git mv`
treatment as Step 8. Decided, per §10 — proceed without further gating.

**Step 12 — Write `docs/testing-guide.md`** (the new, actively-maintained
testing document, §7) after Steps 2–4 have produced the architecture docs
it needs to reference (`architecture/02-workflow-engine.md`,
`architecture/03-permission-model.md`,
`architecture/05-request-state-model.md`) — sequenced last among the
content-creation steps so it can link into a tree that already exists
rather than being written against docs that don't exist yet. Reuse
`testing-manual/`'s structural scaffolding (test-user-alias table, evidence
template, exit criteria) from its archived location; do not reuse its
state/workflow/permission content.

**Step 13 — Complete API Reference Coverage (assigned 2026-07-12).**
`docs/api-reference.md`'s Coverage status section (added in Step 3A) documents
only the primary `EngineRequest` lifecycle, authentication basics,
document/FX-confirmation endpoints, settings, notifications, and report
exports. This step closes the gap by documenting every remaining
registered route family, so `docs/api-reference.md` can drop its "not yet
complete" caveat. Acceptance scope — the exact families already
enumerated in `docs/api-reference.md`'s Coverage status section:

- The full Workflow Designer admin API (`workflow-definitions`,
  `workflow-versions` + `clone`/`validate`/`publish`/`archive`/`graph`,
  `workflow-versions/{v}/stages`, `workflow-actions`,
  `workflow-versions/{v}/transitions`, `workflow-stages/{s}/permissions`,
  `workflow-stages/{s}/field-rules`, `field-groups`, `fields` + `options`).
- Reference data admin (`reference-tables`, `reference-values` +
  activate/deactivate lifecycle).
- Org-structure admin (`organizations`, `teams`, `roles` +
  activate/deactivate, `screens`, `screen-permissions/matrix`).
- `merchants` (full CRUD).
- Governance/compliance (`governance/impact`,
  `banks/{bank}/lifecycle-impact`, `compliance/duplicate-invoices`,
  `compliance/expired-documents`, `compliance/sla-breaches`).
- `ReportController`'s analytics endpoints (`reports/by-bank`,
  `by-currency`, `by-merchant`, `by-sector`, `by-workflow-stage`,
  `requests-over-time`, `sla`, `stage-duration`, `summary`,
  `team-performance`).
- `Profile`/MFA/session management (`api/profile/*`) and `Search`
  (`api/search`, `api/search/recent`).
- Remaining `AuthController` routes (PIN login, password
  forgot/reset/verify, OTP verification, demo-user/demo-role switching).
- The smaller documented gaps: `GET
/api/v1/engine-requests/available-workflows`, `POST
/api/v1/engine-requests/{id}/abandon`, the audit-log async export
  routes.

Re-run `php artisan route:list --path=api` at execution time rather than
trusting this list as final — new routes may exist by then. On
completion, update `docs/api-reference.md`'s Coverage status section to
state full coverage (or a narrowed remaining gap, if new routes were
added in the interim) rather than deleting the section outright — the
verification-method framing (date, command, environment caveat) stays
useful even once coverage is complete.

Each step above should land as its own commit, following the existing
`docs(scope): description` convention this session already used
throughout the audit — this makes the whole migration `git bisect`-able if
any step turns out to have dropped something important.

---

## 10. Decision record — `testing-manual/` disposition

**Decided 2026-07-12: archive, not keep-live.** `testing-manual/` predates
the dynamic workflow engine (legacy status vocabulary throughout,
`executive-member.md`/`committee-director.md` test a removed voting
feature) and must not remain a live reference the QA team could mistake for
current guidance — the same reasoning already applied to `docs/user-view/`.
Every file is preserved in full at `docs/archive/testing-manual/` (§5),
nothing deleted.

This is **not** a gap — `docs/testing-guide.md` (§7) is the replacement:
a new, actively-maintained document covering `runtime_status`,
`current_stage`, `semantic_role`, `final_outcome`, dynamic workflow paths,
capability-based permissions, and organization/data-scope enforcement.
It is written fresh against the current architecture, not a refresh of the
archived content, though it reuses `testing-manual/`'s structural
scaffolding (test-user-alias table, evidence template, exit criteria) since
that scaffolding isn't architecture-coupled.

`docs/user-view/`'s archival remains gated per the Phase F closure
report's own instruction — unchanged by this decision, not bundled with
it. The two directories predate the engine for the same reason but have
different approval requirements: `testing-manual/` had no prior gate, so
this decision authorizes its archival directly (Step 11); `docs/user-view/`
already had an explicit gate from a prior phase, which this plan does not
override (Step 10).

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
5. **Current, architecture-aligned testing guidance** — `testing-manual/`
   is the only test-procedure document that exists, and it's stale (§10).
   Addressed by this revision: `docs/testing-guide.md` is now a planned
   deliverable (§7), not left as an open gap.

---

## 12. What this plan explicitly does not do

- Does not touch `.agents/skills/`, `.claude/skills/`, `.superpowers/`,
  `docs/superpowers/plans+specs/`, `.github/prompts/` — confirmed out of
  scope.
- Does not execute the `docs/user-view/` archival — that remains gated per
  the Phase F closure report's own explicit instruction, re-confirmed here,
  not overridden.
- Does not delete anything — every file's content has a destination
  (merge target, archive location, or "keep as-is"); `testing-manual/` is
  archived in full, not deleted, per the decision in §10.
- Does not yet execute any migration step — §9's 12 steps remain to be
  carried out incrementally, each independently reviewable.

## 13. Status

**Overall direction: APPROVED.** `testing-manual/` disposition: **decided
— archive** (§10), replaced by the new `docs/testing-guide.md` deliverable
(§7). `docs/user-view/` archival: **remains gated** per Phase F, unchanged.

**Execution has not begun.** This revision is submitted for final review
before Step 1 of §9 starts.

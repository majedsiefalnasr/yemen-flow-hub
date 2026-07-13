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
`config('workflow.support_claim_ttl_minutes')` — the config key is not
read by the runtime claim service (it is still read directly by
`backend/database/seeders/Support/EngineRequestScenarioBuilder.php` when
seeding claimed scenarios); both default to 15 minutes today, so this
had not surfaced as a behavioral bug, only a documentation-accuracy one;
(2)
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

**Step 3B — ✅ DONE (2026-07-12).** Pre-flight `git status` confirmed the
baseline (2 pre-existing modified files, 11 pre-existing untracked
files) before touching either file. Used `graphify query` for initial
orientation — broad queries against `graphify-out/graph.json` returned
stale/noisy results (matched old spec docs and vendored framework code
rather than live source), so narrowed to targeted queries (e.g.
`UserActionableRequestQuery DashboardWorkController`), which returned
accurate, current results and confirmed the graph did not need
re-indexing for this task. Dispatched two parallel
`socraticode:codebase-explorer` subagents — one per document — to trace
every claim against `backend/app/` and `frontend/app/` directly, plus
two manual follow-up checks to resolve items the first agent flagged as
not-fully-confirmed.

**`docs/architecture/04-dashboard-architecture.md`** — promotes
`audit-functional/14-dashboard-architecture-decision.md` (a dated,
past-tense proposal) into a present-tense canonical reference; the model
it proposed is now shipped. Verified: the exact frontend routing order
(`system_dashboard` VIEW → `bank_analytics` VIEW → fallthrough
`MyWorkDashboard`), confirmed independently duplicated across
`dashboard.vue` and `index.vue` (same logic, not shared via a
composable — a real architectural fact, recorded rather than silently
smoothed over); the backend's independent gate in
`DashboardStatsService::stats()`'s `match(true)`; the exact, exhaustive
`GET /api/dashboard/work` response shape — **6 top-level keys**
(`actionable`, `claimed`, `tracking`, `sla`, `recent_activity`,
`metrics`), confirmed no others exist — `recent_activity`/`metrics` are
hardcoded empty arrays, fetched by the frontend store but rendered by no
template markup; the actionable-work invariant's three call sites all
resolving to `UserActionableRequestQuery` by direct code reference; that
`SystemAdminDashboard` is an import alias for `CbyAdminDashboard.vue`,
not a separately-named file; and that `DashboardKpiCard.vue` (the one
component containing a `--voting` color-token mapping) has zero callers
anywhere in `frontend/app`, confirmed via a direct follow-up grep after
the first agent flagged it as not fully confirmed. Also confirmed zero
backend widget/metric-catalog code exists (Level 2 is correctly
documented as not-yet-built) and zero per-role dashboard _components_
beyond the 3 that exist today.

**Step 3B accuracy correction (2026-07-12) — dashboard selection is not
capability-only end to end.** An independent review caught that the
original write overstated capability-based selection. Corrected in
`docs/architecture/04-dashboard-architecture.md` and `docs/README.md`:
`dashboard.vue`/`index.vue` gate route _admission_ via
`definePageMeta({ middleware: ['auth', 'role'], requiredRoles: ROUTE_ROLE_MAP['/dashboard'] })`
— a fixed role-code list, checked by `middleware/role.ts`'s
`requiredRoles.includes(auth.user.role)` — before component selection
ever runs. `DashboardStatsService::stats()`'s analytics gate requires
**both** a fixed role code (`hasRoleCode(SYSTEM_ADMIN)`/
`hasRoleCode(BANK_ADMIN)`) **and** the corresponding capability, not
capability alone. The document no longer states "never by role name" or
that a future dynamic executor role reaches `MyWorkDashboard`
automatically with no code change — `ROUTE_ROLE_MAP['/dashboard']` is
that code change. Documented as capability-led frontend _component_
selection with remaining fixed-role constraints in route admission and
backend analytics dispatch — recorded as architecture drift from the
source decision record's proposal, not intended capability-only
behavior.

**Also corrected: the legacy `DashboardStatsService` branches are not
structurally unreachable.** The original write claimed the 6 legacy
workflow-role branches are confirmed unreachable from current frontend
routing. Narrowed: they are unreached **under default seeded capability
assignments**, a weaker claim. Since `system_dashboard`/`bank_analytics`
capabilities are administratively reassignable
(`PUT /api/v1/roles/{role}/screen-permissions`) while the backend gate
remains fixed to specific role codes, granting an analytics capability to
a different role would route the frontend to an analytics component
while the backend fell through to that role's legacy branch — a real
possible mismatch, not merely hypothetical. Whether this is exploitable
today depends on live `screen_permissions` grants, which requires a
database query, runtime configuration inspection, or a targeted
regression test to settle — not asserted either way. The legacy payload
is documented as unreached-under-defaults, not universally dead.

**`docs/architecture/05-request-state-model.md`** — documents the
4-concept model with exact persistence fields, casts, nullability, and
API serialization for each. Confirmed `App\Support\EngineRequestStatus`
(not `App\Enums\EngineRequestStatus`) as the runtime_status source, 5
values; `EngineRequestResource` exposes the value twice (`status` alias

- canonical `runtime_status`). Confirmed `final_outcome`'s exact
  conditional serialization: **absent from JSON** (not `null`) unless the
  current stage is loaded, non-null, and `is_final` — a precision the
  prior documentation lacked. Discovered and documented a mapping not
  previously recorded anywhere: `final_outcome: COMPLETED` produces
  `runtime_status: CLOSED`, not `runtime_status: COMPLETED` (no such
  runtime_status value exists) — flagged as an easy mistake given the two
  enums don't share a naming scheme. Confirmed `CUSTOMS_DECLARATION_ISSUED`
  is dead on the backend (`AuditAction` uses `CUSTOMS_ISSUED`) but found
  **live, unreachable dead-code residue on the frontend**:
  `frontend/app/pages/audit.vue`'s `ACTION_LABELS` map still contains
  `CUSTOMS_DECLARATION_ISSUED` plus 3 dead voting-related keys
  (`VOTE_SUBMITTED`, `VOTING_SESSION_OPENED`, `VOTING_SESSION_CLOSED`) that
  match no case in the live `AuditAction` enum — recorded as cleanup debt,
  not corrected (no production code changed). Documented the
  `WorkflowVersion.state`/`WorkflowStage.status` distinction from
  `runtime_status`, including that `WorkflowStage.status` is a validated
  plain string, not a PHP enum cast (unlike `semantic_role`/`final_outcome`,
  which are).

**Step 3B accuracy correction (2026-07-12) — 5 further corrections in
this same document:**

1. **`EXECUTIVE_REVIEW`/`EXECUTIVE_VOTE` — real frontend drift, not
   clean.** The original write claimed zero legacy-name residue anywhere
   in `backend/app` or `frontend/app`, because it searched for
   `EXECUTIVE_VOTING` and missed the frontend's actual residual spelling.
   `frontend/app/types/models.ts`'s `StageSemanticRole` TypeScript type
   still declares `'EXECUTIVE_VOTE'` as a union member and is **missing
   `'EXECUTIVE_REVIEW'` entirely** — the type does not contain the string
   the backend actually emits. Corrected to document this as real,
   live frontend contract drift, alongside the already-documented
   incomplete `EngineRequestStatus` type. The backend enum (`EXECUTIVE_REVIEW`,
   no `EXECUTIVE_VOTING`/`EXECUTIVE_VOTE` residue) and Executive Voting's
   out-of-V1 status are both preserved as accurate; only the frontend
   "clean" claim was wrong.
2. **The 22-value `RequestStatus` mapping claim was overstated.** The
   original write said every one of the 22 old values maps to a current
   4-field combination. Corrected: retired feature-specific values (the
   voting-session-only ones, e.g. `WAITING_FOR_VOTING_OPEN`,
   `EXECUTIVE_VOTING_OPEN`, `EXECUTIVE_VOTING_CLOSED`) have no live V1
   equivalent, because the feature itself was removed, not renamed. All
   _current_ request state must be represented by the four concepts;
   retired feature-specific values must not be mapped or recreated.
3. **`semantic_role`/`final_outcome` "never combined" is not
   code-enforced.** `StoreWorkflowStageRequest`/`UpdateWorkflowStageRequest`
   validate each field independently with no cross-field guard, and no
   model- or database-level constraint enforces mutual exclusivity.
   Corrected to distinguish the intended architecture and the current
   Import Financing V2 convention (terminal `CLOSED_*` stages keep
   `semantic_role` null, use `final_outcome`) from an enforced invariant;
   also clarified `StageSemanticRole::FINAL` marks the operational
   `FINAL` stage and is not itself a terminal outcome.
4. **Fallback removal criterion 2 ("no consumer relies on the `codes`
   half") was over-concluded from call-site count alone.** The ~40
   call-site figure proves every consumer still _executes_ a query
   containing both the `semantic_role` and `code` conditions — it does
   not prove any live record's _result_ actually depends on the `codes`
   half being the decisive clause. Corrected to require a live database
   query, runtime telemetry, or a targeted regression test to settle
   actual data dependence, and to state plainly that static analysis
   proves no roles-only consumer path exists (so removal would require
   changing the shared implementation) without claiming runtime
   dependence is "confirmed not met." The overall conclusion — none of
   the 4 criteria confirmed satisfied, do not remove the fallback — is
   unchanged and still holds.
5. **The "structurally prevented" claim about `INACTIVE` stages was
   partly wrong and overstated where accurate.** The original write
   claimed an unreachable `INACTIVE` stage "can coexist" within a
   `PUBLISHED` version because the rule pack "only checks reachable
   stages" — false: `WorkflowPublishRulePack::validateReachability()`
   rejects **every** unreachable stage regardless of `status`, with no
   activity-status exemption. Corrected to state unreachable `INACTIVE`
   stages cannot survive the supported publish path at all. Separately,
   the claim that an `ACTIVE` request on an `INACTIVE` stage is
   "structurally prevented"/impossible was too broad: the guarantee holds
   only for the _validated publish/edit path_ (a version published
   through `POST .../publish` and never modified outside it) — no
   database constraint independently guarantees it for externally
   modified writes or legacy pre-validator data. Corrected to state this
   precisely rather than as a universal database-level guarantee.

**Cross-document link activation.** Replaced every live "planned, not
yet written — Step 3B" annotation pointing at these two files with real
links, across `docs/README.md` (2 table rows), `docs/architecture/README.md`
(rewritten Live/Planned lists), `docs/development-guide.md`,
`docs/architecture/03-permission-model.md` (2 spots),
`docs/architecture/01-system-architecture.md` (2 spots),
`docs/architecture/06-database-and-models.md` (2 spots), and
`docs/engine/extension-guide.md`. Confirmed via `grep` that zero
"planned...Step 3B" annotations remain anywhere outside
`docs/audit-functional/`. `AGENTS.md` was deliberately **not** rewritten
— its consolidation remains Step 9, per your explicit instruction not to
begin it early.

**Verification.** Ran Prettier on all 10 touched files; stable on rerun.
Extracted and resolved every internal Markdown link across all Step
3B-touched files against the filesystem — all resolve. Confirmed via
`git status` that only the intended files are dirty; the 2 pre-existing
dirty files and 11 pre-existing untracked files from before Step 3B
began remain unchanged. Verified the committed blobs with
`git diff --stat HEAD -- <touched-files>` — empty, confirming no
recurrence of the `307ead39` staging issue.

**Deviations**, disclosed rather than absorbed: (1) both new documents
surfaced real drift beyond what the source decision record and AGENTS.md
described — the frontend routing duplication, the `EngineRequestStatus`
type's missing 2 values, and the `audit.vue` dead-code residue were not
previously documented anywhere; all are recorded as findings/cleanup
debt, not silently fixed, since fixing them is a code change out of
scope for a documentation step. (2) Two items in the first verification
agent's report (`DashboardKpiCard.vue`'s caller graph,
`reports/index.vue`'s `tone="voting"` context) were flagged as not fully
confirmed; both were resolved with a direct follow-up grep/read before
being cited in either document, rather than left as hedged claims.

**Step 3B consistency correction (2026-07-12).** The prior correction
round (commit `9045cb03`) fixed all 8 requested overstatements, but 4
residual statements elsewhere in `docs/architecture/04-dashboard-architecture.md`
and `docs/README.md` still implied the fully capability-only model the
corrections had already disproven — leftover from earlier drafting, not
new findings. Fixed:

1. The document's own introduction still said the source decision
   record's model "is now shipped" unqualified. Narrowed: the two-family
   _component structure_ is shipped; the _capability-only end-to-end
   selection model_ the decision record proposed is not, since fixed-role
   constraints remain in route admission and backend analytics dispatch.
2. The Executive Voting section called `executiveMemberStats()`/
   `committeeDirectorStats()` "currently-unreachable," restating the
   already-corrected "structurally unreachable" claim in different words.
   Replaced with the already-established accurate scope: unreached under
   default seeded capability assignments, potentially reachable after an
   administrative capability reassignment.
3. The "Prohibited patterns" section still had a "No role-name routing
   branch" heading, overbroad because fixed-role route admission exists.
   Component selection itself has no `role === 'X'` branch — that part
   of the original claim was accurate. But route admission is
   nevertheless role-based: it is a fixed-role membership gate using
   `ROUTE_ROLE_MAP['/dashboard']` together with
   `requiredRoles.includes(auth.user.role)`, not a literal equality
   conditional, and it is already documented above as existing
   architecture drift. The prohibited rule was therefore narrowed to "do
   not add a per-role component-selection branch," rather than claiming
   no role-based check exists anywhere in the routing path.
4. `docs/README.md`'s permissions summary said "Permissions are
   capability-based, not role-name checks scattered through code" —
   too broad given the dashboard corrections. Replaced with: screen
   capabilities and workflow stage permissions are the primary
   authorization systems; a small number of explicit fixed-role guards
   still exist alongside them and must be documented individually, with
   a link to the dashboard-specific instance.

Searched all live dashboard documentation for `currently-unreachable`,
`never by role`, `no role-name routing`, and unqualified "model is now
shipped" — zero remaining hits in `docs/architecture/04-dashboard-architecture.md`
or `docs/README.md`. A broader sweep found one more hit outside the
scope of this correction: `AGENTS.md:276` still says "never by role
name" — left untouched, since `AGENTS.md`'s consolidation is Step 9 and
you have twice explicitly instructed not to begin it early.
`docs/architecture/05-request-state-model.md` was not touched — it
already passed the prior focused accuracy review and needed no further
changes.

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
(`config('workflow.support_claim_ttl_minutes')` is not read by the
runtime claim service, though the seeding path still reads it directly;
the live value is `AdminSettingsService`'s `support_claim_ttl`, same
finding as the Step 2 corrections), and removed the entire "Voting"/"Allowed
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
are annotated planned. Re-ran `php artisan route:list --path=api`, which
completed successfully; the total route count is not recorded as a fixed
number, per item 1, because demo/switch-role routes register
conditionally on `config('demo.allowed_environments')` and the total is
environment-dependent. Confirmed via `git status` that the three
documentation files (`api-reference.md`,
`architecture/06-database-and-models.md`,
`architecture/01-system-architecture.md`) plus this plan record were the
four intended changed files, and that only those four are dirty; the 2
pre-existing dirty files (`.codex/config.toml`,
`docs/audit-functional/12-phase-b-checkpoint.md`) and 11 pre-existing
untracked files remain unchanged.

**Step 3A accuracy correction, round 2 (2026-07-12).** An independent
review of commit `b4f8bdb3` confirmed it signed, co-authored, exactly the
4 intended files, and Prettier-clean — but caught 2 remaining
inaccuracies:

1. `docs/api-reference.md`'s Executive Voting section claimed "no
   vote-casting UI anywhere in the shipped frontend" and then documented
   only `backend/app` cleanup debt, omitting frontend residue entirely.
   Traced every frontend voting reference individually before writing
   anything (not assumed dead from the symbol name): `VoteType`
   (`frontend/app/types/enums.ts`) and a `vote: VoteType` model field —
   zero consumers outside declarations/tests; the
   `action.voting.cast`/`action.voting.close_finalize` role-surface
   capability strings (`frontend/app/constants/role-surfaces.ts`) —
   listed in every role's capability catalog but never queried by any
   composable/store/component at runtime; `voting_session_timeout`/
   `secret_voting` (`useAdminSettings.ts`) and `voting_analytics`
   (`useReports.ts`) typed fields — referenced only in unit-test
   fixtures, never rendered; a dead CSS rule
   `.notification-row--voting` in `notifications.vue` — never
   conditionally applied. Confirmed zero voting page files and zero
   voting route/middleware registration anywhere in `frontend/app`.
   Separately confirmed that every `var(--voting)`/`tone="voting"` hit in
   `ActionRequiredStrip.vue`, `MetricCard.vue`, `ActiveReviewBanner.vue`,
   `DashboardKpiCard.vue`, and `reports/index.vue` is the shared design
   color token (indigo) styling unrelated KPI/banner content, not voting
   functionality — explicitly not counted as residue, per your caution
   against conflating color tokens with real voting code. Rewrote the
   section to state the precise claim ("no live or mounted V1 voting
   route, screen, or action surface") separately from the itemized
   Backend/Frontend residue lists, plus a "Not residue" callout for the
   color-token usages.
2. This plan's own Step 3A correction record still stated the hardcoded
   "237 registered API routes" count that item 1 of the previous
   correction round had already removed from `api-reference.md` itself —
   an inconsistency between the doc and its own change-log entry.
   Replaced with "`route:list` completed successfully; total is
   environment-dependent." Also replaced the ambiguous "only the 4
   corrected files plus this record are dirty" with an explicit
   enumeration: the three documentation files were the changed content,
   this plan record is the fourth intended file, together they are "the
   four."

Re-ran Prettier on both files (`api-reference.md`, this plan doc) —
clean and stable on rerun. Re-validated internal Markdown links in both
files against the filesystem — all resolve. Confirmed via `git status`
that only these 2 files are dirty; the 2 pre-existing dirty files and 11
pre-existing untracked files remain unchanged. Verified the committed
blobs post-commit with `git diff --stat HEAD -- <files>` to confirm no
recurrence of the earlier staging problem from commit `307ead39`.

**Step 4 — Rewrite the 3 heavily-stale files**
(`docs/01-workflow-and-business-rules.md` → merges into
`docs/architecture/02-workflow-engine.md`, `docs/04-frontend-guide.md` →
`docs/frontend-guide.md`, `docs/05-backend-guide.md` →
`docs/backend-guide.md`). Higher risk since more content is being deleted
(voting sections) — do these one at a time with a diff review each time,
not as a batch.

**Step 4A — ✅ DONE (2026-07-13).** Scope: only
`docs/01-workflow-and-business-rules.md` →
`docs/architecture/02-workflow-engine.md`. `docs/04-frontend-guide.md` →
`docs/frontend-guide.md` and `docs/05-backend-guide.md` →
`docs/backend-guide.md` were explicitly held for Step 4B/4C and not
started.

Pre-flight `git status` confirmed the baseline (2 pre-existing modified
tracked files — `.codex/config.toml`,
`docs/audit-functional/12-phase-b-checkpoint.md` — plus 12 pre-existing
untracked files: 11 under `docs/audit-functional/` plus
`backend/app/Console/Commands/RecreateActiveRequestsUnderV2Command.php`)
before touching anything; the same baseline was confirmed unchanged
post-flight.
Used `graphify query` for initial orientation and SocratiCode
symbol/impact/search/flow analysis before documenting
`EngineTransitionService`, `WorkflowDesignerService`,
`WorkflowVersionValidator`, `SemanticResolver`, and `StageHookRegistry`.

**Source verification performed.** Read
`docs/01-workflow-and-business-rules.md` in full (696 lines) before
deletion. Retrieved the former `docs/02-system-architecture.md`'s engine
section via `git show 307ead39^:docs/02-system-architecture.md` (deleted
in the Step 3A commit) per instruction to use Git history. Read
`docs/decisions/semantic-mapping.md` in full. Read the relevant section
of `docs/05-backend-guide.md` (lines 111–220) for context only — the file
itself was not modified, per instruction. Read
`EngineTransitionService::execute()`/`saveDraft()`/`abandonDraft()`/
`resolveStatusAfterTransition()`,
`WorkflowDesignerService::publishVersion()`/`ensureValidStateTransition()`/
`cloneVersion()`, `WorkflowVersionValidator::validate()`,
`SemanticResolver::publishErrors()`/`publishWarnings()`,
`StageHookRegistry`, and the `WorkflowActionKind`/`WorkflowTransitionType`
enums directly from current backend source, treating source as
authoritative over every legacy document as instructed.

**Inaccuracies discovered in legacy/existing docs (not silently
inherited):**

1. The legacy `docs/01-workflow-and-business-rules.md` claimed a
   code-enforced `bank_reject_terminal` separation-of-duties guard
   ("the creator cannot reject their own request"). Grepped
   `backend/app/` directly (Policies, Http/Requests, Services/Workflow)
   for SOD/self-reject/same-user logic and independently dispatched a
   background SocratiCode agent to do the same search; both found zero
   matches. `StagePermissionResolver` grants EXECUTE purely from
   `stage_permissions` rows — no comparison against
   `EngineRequest.created_by` exists anywhere. The new document states
   this plainly in its "Separation of duties: convention, not a code
   guard" section rather than carrying the false claim forward.
2. `docs/api-reference.md`'s "editable states" sentence cited
   `docs/01-workflow-and-business-rules.md` and named specific status
   values (`DRAFT_REJECTED_INTERNAL`, `BANK_RETURNED`,
   `SUPPORT_RETURNED`) as the editability gate. These are not real
   `EngineRequestStatus` values, and `saveDraft()`'s actual gate (read
   directly) is `runtime_status: ACTIVE` + EXECUTE permission + claim
   held — the same gate as `execute()`, not a fixed status-name
   whitelist. Corrected in place with an anchor link into the new
   document's `saveDraft()` subsection.

**Removed content** (all itemized in the new document's own "What this
document removes from the legacy source" section, so it is not
duplicated here): the fixed 18-value status diagram/vocabulary; Executive
Voting stage/session/tally behavior as active engine functionality
(explicitly stated as out-of-V1, not zero-code, linking to
`api-reference.md`'s cleanup-debt inventory rather than describing dead
symbols as live); "Voting Service"/centralized fixed-workflow language;
fixed per-role workflow paths ("Owner: Bank SWIFT Officer," etc.); stale
route families/static transition endpoints; the claim that a
nonexistent `current_status` column or a frontend `RequestStatus` enum
drives the engine; customs-declaration terminology for the current
Director/FX-confirmation workflow.

**Migration.** Tested whether `git diff --cached --stat -M50%` would
detect a rename from the old path to the new one before choosing
delete+add — it did not (695 deletions / 446 insertions, too dissimilar
even at a lenient 50% threshold), so `git rm` +
`git add docs/architecture/02-workflow-engine.md` is the correct
representation, not a shortcut around the "prefer a history-preserving
move" instruction.

**Link updates** — every live reference to the old path was updated to
point at the new document (repo-wide `grep` confirmed zero remaining
live references afterward; the only surviving matches are this plan's
own historical Step 3A/4 text, the new document's own verification-banner
citation of the old path by name, and pre-existing `docs/superpowers/`
planning artifacts, all correctly out of scope):

- `docs/README.md` — Workflow engine row: planned → **live**.
- `docs/architecture/README.md` — moved `02-workflow-engine.md` from
  "Planned, not yet written" to "Live"; removed the now-dangling
  authority pointer to the deleted file.
- `docs/api-reference.md` — real content correction (inaccuracy #2
  above), not just a link swap.
- `docs/architecture/06-database-and-models.md` — "Dynamic workflow
  engine tables" intro now links directly, no "planned" caveat.
- `docs/architecture/03-permission-model.md` — stage-graph sentence now
  links directly.
- `docs/engine/README.md` — canonical architecture doc marked live
  instead of planned.
- `docs/engine/extension-guide.md` — entity-chain pointer now links
  directly.
- `AGENTS.md` — minimal path-only correction on the doc-authority list
  (line 161 → now cites `docs/architecture/02-workflow-engine.md`); no
  other change to that file, per instruction not to perform its Step 9
  consolidation here.
- `frontend/CLAUDE.md`, `backend/CLAUDE.md` — Docs Reference list entries
  repointed.
- `backend/README.md` — **deviation, not in the user's named file list**:
  found during the repo-wide stale-reference sweep required by the
  "update every live reference" instruction. Its workflow-status ASCII
  diagram is legacy content out of scope for this step, but the sentence
  citing `docs/01-workflow-and-business-rules.md` was a genuinely broken
  link after the delete, so only that citation sentence was corrected
  (reframed as historical, pointed at the new doc) — the diagram itself
  was left untouched.

**Checks performed.** Self-reviewed the new document against the 4
forbidden-content categories (voting-session behavior, old status
vocabulary, fixed-role workflow paths, customs terminology) via targeted
`grep` — all matches found were inside the intentional "what this
document removes" section, none reintroduced as active behavior. Wrote a
script-based Markdown link/anchor checker covering all 12 touched files
(path resolution + heading-to-slug matching using GitHub's slugger
algorithm) — found and fixed one bad anchor
(`#saveDraft--not-gated-by-a-fixed-editable-states-list` →
`#savedraft-not-gated-by-a-fixed-editable-states-list`; em-dashes are
stripped, not converted to hyphens, by GitHub's slugifier) before
reaching zero broken links. Ran Prettier on all 12 touched files
individually (the batched run intermittently hit a `frontend/`-local
`prettier-plugin-tailwindcss` config-resolution error unrelated to these
files; per-file runs from the correct working directory all passed) and
confirmed `--check` stability on rerun. Read every formatted file back;
found no stray leading `+`/`-` list artifacts. Found and fixed one real
content defect during the read-back: the `resolveStatusAfterTransition()`
code quote had a `[...]` truncation placeholder instead of the real
`Log::warning()` array arguments — replaced with the exact source.

**Deviations from the instructions as given:** `backend/README.md`
(above) — not in the named file list, touched only for its broken link,
not its stale diagram content, which stays out of scope. No other
deviations.

**Blockers:** none.

**Files changed this step:** commit `4fba9d2e` changed 14 paths —
`docs/01-workflow-and-business-rules.md` (deleted),
`docs/architecture/02-workflow-engine.md` (created), 11 files with
link/content corrections (`docs/README.md`, `docs/api-reference.md`,
`docs/architecture/03-permission-model.md`,
`docs/architecture/06-database-and-models.md`,
`docs/architecture/README.md`, `docs/engine/README.md`,
`docs/engine/extension-guide.md`, `AGENTS.md`, `frontend/CLAUDE.md`,
`backend/CLAUDE.md`, `backend/README.md`), and this plan document
itself. After the deletion, 13 files touched by this step remain on
disk and are eligible for formatting/link checks (the deleted file is
not). A separate correction commit, `609a5ff9`, later fixed a
pre-commit-hook formatting-drift bug in `4fba9d2e` and changed only
`docs/architecture/02-workflow-engine.md` (see the Step 4A accuracy
correction record below for what it fixed). Pre-existing dirty/untracked
baseline (2 modified tracked files, 12 untracked files) confirmed
untouched throughout.

**Step 4A accuracy correction (2026-07-13).** A focused review caught 8
inaccuracies in the newly-created `docs/architecture/02-workflow-engine.md`
and 2 in files it touched, all re-verified directly against current
backend source before correcting:

1. **Designer-managed entities conflated version-scoped and global
   gating.** The doc said everything (including actions) is "gated to
   `DRAFT`-state versions." `WorkflowAction` has no
   `workflow_version_id` and is never gated by any `WorkflowVersion`
   state — `WorkflowActionService`/`WorkflowActionController` manage it
   globally. Split into a version-scoped/`DRAFT`-gated list (stages,
   transitions, stage permissions, field groups/definitions/stage field
   rules) and a global/never-gated list (actions). Also documented,
   verified directly against `UpdateWorkflowActionRequest` and
   `WorkflowActionController`/`WorkflowActionService`: `code` is
   immutable (enforced by the request's `after()` validator, which
   audits change attempts); `name` **and `kind`** are both editable
   (the doc previously implied only `name`); `is_active` changes only
   through `activate`/`deactivate`; in-use actions cannot be deactivated
   or deleted; `isProtected()` blocks deletion only — nothing in source
   restricts renaming or re-kinding a protected action, so the doc no
   longer generalizes that. Flagged `WorkflowActionService`'s own class
   doc comment ("`code` is immutable; `name` and `is_active` are
   editable") as stale — it omits `kind`.
2. **DRAFT editability wrongly attributed entirely to
   `WorkflowDesignerService`.** `WorkflowDesignerService` has no
   field-group/field-definition/stage-field-rule methods at all — those
   live in `FieldDesignerService`, which independently checks
   `WorkflowVersion.isEditable()` at its own gate. Corrected both the
   "Designer-managed entities" section and the "DRAFT editability"
   section to attribute stage/transition/permission gating to
   `WorkflowDesignerService` and field-entity gating to
   `FieldDesignerService` separately.
3. **Publish-validation inventory was incomplete.** Missing from the
   inline `WorkflowVersionValidator` checks: an initial stage cannot
   grant EXECUTE to a non-banking organization
   (`INITIAL_STAGE_NON_BANKING_EXECUTOR`, checked against
   `Organization.classification`). Missing from the
   `WorkflowPublishRulePack` list: `validateInitialSubmitAmbiguity()`
   (`INITIAL_SUBMIT_AMBIGUOUS` when an initial stage has multiple
   outgoing transitions without exactly one flagged
   `is_default_submit`) and `validateInactiveReferenceTables()`
   (`INACTIVE_REFERENCE_TABLE`). Both read directly from
   `WorkflowVersionValidator.php` and `WorkflowPublishRulePack.php`
   before being added.
4. **Semantic publish-gate section understated the blocking errors and
   the `SEMANTIC_MAPPING_MISSING` condition.** The doc named only
   `SEMANTIC_MAPPING_MISSING` as blocking; `SemanticResolver::publishErrors()`
   also fires `SEMANTIC_MAPPING_AMBIGUOUS` (more than one field declares
   the same explicit `semantic_tag`) as a second blocking error. And
   `SEMANTIC_MAPPING_MISSING` doesn't fire merely because "no field
   declares" a tag — `fieldForTag()` (read directly) tries the explicit
   `semantic_tag` first, then falls back to
   `SemanticRegistry::fieldKeyAliases()`; the error only fires when
   _both_ resolution paths fail. Corrected to state the exact condition,
   not the simplified one.
5. **The two semantic fallback maps were collapsed into one "code-alias
   map."** `SemanticResolver::stageForRole()` falls back through
   `SemanticRegistry::stageCodeAliases()`; `fieldForTag()` falls back
   through a **different** map, `SemanticRegistry::fieldKeyAliases()`.
   Split the "compatibility fallback" section into two explicit
   bullets, one per resolution kind, rather than describing a single
   shared map.
6. **Notification transaction semantics overstated what rolls back —
   corrected twice.** The doc's original blanket claim ("a failure at
   any step rolls back the entire transition atomically") was
   inaccurate for step 16, so a first pass narrowed the rollback claim
   to steps 1–15. That narrowing itself overstated the gap: reading
   `EngineNotificationDispatcher::afterTransition()` directly shows
   recipient resolution (`executeHolderIds()`,
   `scopeRecipientsForRequest()`) and the `DB::afterCommit()`
   registration call both run **synchronously, still inside the
   transaction** — a failure there rolls back exactly like steps 1–15.
   Only the callback body, `DispatchNotification::dispatch()`, runs
   after a successful commit and cannot roll back an already-committed
   transition. The canonical doc now describes step 16 as two phases
   (synchronous recipient-resolution/registration vs. deferred dispatch)
   rather than treating "step 16" as one uniformly non-rolling-back
   unit. Also
   corrected `saveDraft()`'s claim description: it requires a held claim
   only when the current stage has `requires_claim: true`
   (`EngineClaimService::ensureClaimHeld()` no-ops otherwise, read
   directly) — the doc previously implied an unconditional claim-held
   check.
7. **Claim-TTL config key described as globally unused.** True for the
   runtime claim service, but
   `backend/database/seeders/Support/EngineRequestScenarioBuilder.php`
   reads `config('workflow.support_claim_ttl_minutes', 15)` directly
   when constructing claimed-request seed scenarios. Corrected the
   canonical doc and the two other places in this plan that described
   the same finding with the same "unused" overstatement (the Step 2
   correction record above and the Step 3A `docs/06-api-reference.md`
   migration note) — `03-permission-model.md` and `api-reference.md`'s
   existing "not read by the claim service" wording was already
   accurate and needed no change.
8. **`docs/api-reference.md`'s `status` filter listed only 3 of 5
   allowed values.** `EngineRequestListQuery::ALLOWED_STATUSES` (read
   directly) is `ACTIVE`, `CLOSED`, `REJECTED`, `CANCELLED`,
   `ABANDONED`. The doc listed only the first three. Corrected.

**Baseline and file-accounting corrections.** The original Step 4A
record undercounted the untracked baseline by one (11 instead of 12 —
omitted `backend/app/Console/Commands/RecreateActiveRequestsUnderV2Command.php`,
which predates this step and is unrelated to it) and gave internally
contradictory file totals ("plus 10 files," "11 files in that list,"
"12 total"). Both corrected above, in place, rather than left standing
next to the accurate numbers.

**Verification for this correction pass:** re-checked all 8 claims
directly against current backend source before editing (not against the
user's restated claims alone). Ran Prettier on all 13 extant Step 4A
files individually from the correct working directory (frontend-scoped
files from `frontend/`, everything else from repo root); confirmed
`--check` stability on rerun. Read every formatted file back for
line-leading `+`/`-` artifacts — none found. Re-ran the link/anchor
checker across the same 13 files — zero broken links. Searched the
canonical document for the corrected wording (action editability,
publish-validation rule names, notification/after-commit language,
claim-TTL seeder reference, the two fallback-map names) to confirm each
landed. Confirmed `git status` reports exactly 2 modified tracked files
and 12 untracked files, matching the corrected, pre-existing baseline.

**Blockers:** none.

**Step 4A — ✅ APPROVED.** Independent verification of commit
`919c951c` confirmed: notification rollback phases match source,
Prettier passes across all 13 files, internal links resolve, the commit
is signed with the required co-author, the working tree matches the
2-modified/12-untracked baseline, and the three committed files have no
post-commit diff.

**Step 4B — ✅ DONE (2026-07-13).** Scope: only
`docs/04-frontend-guide.md` → `docs/frontend-guide.md`.
`docs/05-backend-guide.md` → `docs/backend-guide.md` (Step 4C) was not
started.

Pre-flight `git status` confirmed the baseline (2 modified tracked
files, 12 untracked files) before touching anything; confirmed unchanged
post-flight.

**Source verification performed.** Read `docs/04-frontend-guide.md` in
full (896 lines) before deletion. Read all four mandatory frontend
context files (`frontend/PRODUCT.md`, `frontend/DESIGN.md`,
`frontend/SHADCN.md`, `frontend/CLAUDE.md`) before drafting. Verified
directly against `frontend/app/`: the full `app/pages/` tree (34 page
files — substantially more than any prior route list documented);
`dashboard.vue`/`index.vue`'s capability-family routing
(`can('system_dashboard', 'VIEW')` → `can('bank_analytics', 'VIEW')` →
`MyWorkDashboard` fallthrough); `app/middleware/role.ts` and
`ROUTE_ROLE_MAP` in `app/constants/workflow.ts` (most entries derived
via `rolesForSurface()`, but `/admin`, `/admin/health`,
`/settings/system`, `/settings/bank` are hardcoded fixed-role arrays);
`app/pages/customs/index.vue` (legacy URL alias, content entirely
external FX confirmation terminology); `BankAdminDashboard.vue`'s
`RUNTIME_STATUS_BADGE` map (matches `frontend/DESIGN.md` §7's citation);
confirmed no `RequestStatus` enum exists in `app/types/enums.ts`.

**Voting-residue check (a real risk given the file's legacy voting
content).** Grepped the entire `frontend/app/` tree for "voting" and
individually verified every match rather than trusting a blanket
zero-result: `--voting` is a reused generic "indigo" design token
(`ActionRequiredStrip.vue`, `ActiveReviewBanner.vue`,
`DashboardKpiCard.vue`, `MetricCard.vue`), not feature UI;
`audit.vue`'s `ACTION_LABELS` map has dead
`VOTE_SUBMITTED`/`VOTING_SESSION_OPENED`/`VOTING_SESSION_CLOSED` label
strings for historical audit-log display; `useReports.ts` declares an
unrendered optional `voting_analytics?` field (confirmed zero renders
anywhere in `app/pages`/`app/components`); `useAdminSettings.ts`
declares unused `voting_session_timeout`/`secret_voting` setting
fields. None of this is active voting UI — no `/voting` route, no
voting store, no voting composable exist. The new document states this
precisely rather than either claiming zero related code (false) or
describing the residue as live functionality (also false), and links to
`api-reference.md`'s Executive Voting section for the full cleanup-debt
inventory rather than re-inventorying it.

**Dashboard/route admission — corrected to avoid overstating
capability-only behavior, per your explicit instruction.** The new
document documents dashboard _component_ selection as purely
capability-led (verified: the `can()` check chain has no role-name
branch), but also documents that both `dashboard.vue` and `index.vue`
still carry `requiredRoles: ROUTE_ROLE_MAP['/dashboard']` via the `role`
middleware — a still-present route-admission gate underneath the
capability-led component choice. Also documented the small set of
routes in `ROUTE_ROLE_MAP` that are hardcoded fixed-role arrays rather
than capability-derived (`/admin`, `/admin/health`,
`/settings/system`, `/settings/bank`), matching this plan's and
`AGENTS.md`'s existing "a small number of explicit fixed-role guards
still exist alongside [capabilities]" framing rather than contradicting
it.

**Preserved:** the two-family dashboard model (with the corrected
route/component split above); the Design Consistency Requirement and
operational density composition table (Distraction-free/Operational
queue/Governance tiers); the support-claim-heartbeat spec; the
corrected routes already fixed in the legacy file during Phase F
(`/workflows/*` replacing `/requests/*`, no `/voting/*` routes); the
four-field request-state model pointer (not duplicated, linked to
`architecture/05-request-state-model.md`).

**Removed:** the fixed 18-value status vocabulary and "Internal →
Simplified Status Mapping" section; the full "Voting UI" section (Vote
Types, Voting Session UX, Voting Interface Requirements) and "Suggested
Navigation by Role" voting nav items (Waiting For Voting Open, Voting
Session Management, etc.) as if still live; fixed per-role navigation
lists as the access-control model, and the suggested-but-never-built
per-role middleware files (`bank-reviewer.ts`, `executive.ts`,
`admin.ts`) presented as shipped; customs-declaration framing for the
Director/FX-confirmation workflow (the new document instead documents
`/customs` as a legacy URL alias whose content is FX confirmation);
the stale citation to a status enum sourced from
`docs/03-database-and-models.md` (that path no longer exists).

**Migration.** Tested rename detection the same way as Step 4A —
`git diff --cached --stat -M50%` after `git rm` + `git add` did not
detect a rename (896 deletions / 308 insertions, too dissimilar), so
delete+add is the correct representation.

**Link updates** — every live reference to the old path repointed:
`docs/README.md` (Frontend guide row: planned → **live**),
`docs/architecture/README.md` (added the new doc to the live list,
outside the `architecture/` tree; changed the "not yet migrated" note
from Step 4B to Step 4C, since only the backend guide remains),
`AGENTS.md` (minimal path-only correction on the doc-authority list),
`frontend/CLAUDE.md` (Docs Reference entry repointed). The two
`docs/audit-functional/19-phase-f-inventory.md` /
`20-phase-f-checkpoint.md` matches and the `docs/superpowers/` match are
historical audit/planning records describing a fix made to the
old file at that time — correctly left untouched, same treatment as
Step 4A's historical references. `docs/00-project-brief.md` has no
reference to the old path; nothing to fix there.

**Checks performed.** Self-reviewed the new document against the 4
forbidden-content categories (voting-session behavior, retired status
values, fixed per-role workflow paths, customs-facing terminology) via
targeted `grep`. **Correction (see the accuracy-correction record
below):** the original record here claimed "zero matches, nothing to
itemize as an exception" — that was inaccurate. The document does
contain voting, retired-status, and customs terms, all confined to
intentional contexts (the verification-banner citation of the legacy
file, route-removal statements, the Executive Voting cleanup-debt
section, and the "what this document removes" itemization) — every
match was reviewed individually and none reintroduces the removed
functionality as active, but the original "zero matches" framing was
wrong and has been corrected. Ran Prettier on every touched Markdown
file individually from the correct working directory (frontend-scoped
files from `frontend/`) and confirmed `--check` stability on rerun.
Read every formatted file back for line-leading `+`/`-` artifacts —
none found. Ran the link/anchor checker across all touched files —
zero broken links.

**Deviations:** none.

**Blockers:** none.

**Files changed this step:** commit `7f808d98` changed 7 paths —
`docs/04-frontend-guide.md` (deleted), `docs/frontend-guide.md`
(created), 4 files with link/content corrections (`docs/README.md`,
`docs/architecture/README.md`, `AGENTS.md`, `frontend/CLAUDE.md`), and
this plan document itself. After the deletion, 6 files touched by this
step remain on disk and are eligible for formatting/link checks. A
separate correction commit, `21cb1893`, later fixed a pre-commit-hook
formatting-drift bug in `7f808d98` and changed only
`docs/frontend-guide.md`. No production frontend or backend code was
changed.

**Step 4B accuracy correction (2026-07-13).** An independent review
found 6 issue groups in `docs/frontend-guide.md`, all re-verified
directly against current frontend source before correcting:

1. **Stack version.** The doc said "Vue 4." `frontend/package.json`
   declares `"vue": "^3.5.13"`. Corrected to Vue 3.5; Nuxt 4 was already
   correct and unchanged.
2. **Route inventory undercount and gap.** `frontend/app/pages/`
   contains 35 `.vue` files, not 34 — the doc's route list also omitted
   `/admin/email-templates` and `/admin/email-templates/[type]`.
   Corrected the count and added the missing routes; explicitly
   distinguished 35 source files from 34 distinct URL paths (the
   `settings.vue` parent + `settings/index.vue` child both resolve to
   `/settings`, a normal Nuxt nested-route pattern, not a duplicate);
   removed the word "full" from the route-list framing and added a
   re-verify note instead.
3. **Route-admission mechanism was wrong.** The doc claimed most
   `ROUTE_ROLE_MAP` entries are "capability-derived." Read
   `rolesForSurface()` (`app/constants/role-surfaces.ts`) directly: it
   filters the hardcoded `ROLE_SURFACE_MATRIX` — a static compile-time
   table — and never reads `screen_permissions` or calls `can()`. Read
   `app/middleware/screen.ts` directly: it _does_ call
   `useScreenPermissions().can()` against live, backend-provided,
   client-hydrated `auth.screenPermissions`, and gates a genuinely separate set of
   routes (`/workflows*`, several `/admin/*` governance pages,
   `/bank/users`) via `requiredScreen`, not `ROUTE_ROLE_MAP` at all.
   Verified the 4 `ROUTE_ROLE_MAP` literal-array entries
   (`/admin`, `/admin/health`, `/settings/system`, `/settings/bank`)
   and additionally found (not in the original document) 4 more pages
   with page-local literal role arrays entirely outside
   `ROUTE_ROLE_MAP`: `/admin/email-templates`,
   `/admin/email-templates/[type]`, `/staff`, and `/reports`
   (`REPORTING_ROLES`, a local array literal). Rewrote the section to
   describe the actual mixed model: `screen` middleware = live
   capability check on a specific route subset; `role` middleware
   (whether via `ROUTE_ROLE_MAP`'s `rolesForSurface()` calls,
   `ROUTE_ROLE_MAP`'s literals, or page-local literals) = static role
   whitelist everywhere else.
4. **Dashboard explanation overstated capability-only behavior.**
   Corrected per the same source facts: dashboard route admission
   (`requiredRoles: ROUTE_ROLE_MAP['/dashboard']` →
   `rolesForSurface('nav.dashboard')`) is a static role whitelist, not
   a capability check; only the _component_ selection inside the page
   (`can('system_dashboard', 'VIEW')` → `can('bank_analytics', 'VIEW')`
   → fallthrough) is capability-led. Removed the "two capability-gated
   layers" framing; the document now describes it as a static role gate
   at the route, then a capability-led choice inside it.
5. **Request-state claim overstated shipped consumer behavior.** The
   doc said every consumer already reads the four canonical fields and
   builds presentation from them. Read `frontend/app/types/models.ts`
   directly: `EngineRequestStatus` is `'ACTIVE' | 'CLOSED' | 'REJECTED'`
   (3 cases, not the backend's 5); `EngineRequest.status` still uses
   that truncated type (the field is not named `runtime_status`);
   `EngineRequest` has no `final_outcome` field at all; its
   `current_stage` sub-type has no `semantic_role`. Confirmed
   `BankAdminDashboard.vue` reads `row.original.status` directly,
   matching the type as it stands. Rewrote the section to keep the
   canonical model as the required direction while stating the type
   drift as current, real, unaddressed fact — and narrowed
   `BankAdminDashboard.vue`'s citation to "reference for runtime-status
   severity-token mapping," not "reference for the complete state
   model."
6. **Claim-heartbeat section invented behavior not in source.** The doc
   said heartbeat applies to Support Committee users and that claim loss
   redirects to the queue with a notification. Read
   `useEngineClaim.ts` directly: no role check anywhere in the
   composable — it is generic to `requestId`/`currentUserId`, usable by
   any executor on any claim-required stage. Read `markClaimLost()`
   directly: it sets `claimLost`, clears `claimedBy`, and stops the
   heartbeat — no `navigateTo()` call, no notification dispatch
   anywhere in the file. Read the request-detail page
   (`app/pages/workflows/instances/[id].vue`) directly: on `claimLost`
   it renders an inline destructive `Alert` with a manual "return to
   queue" button and, conditionally, a manual retry button — nothing
   navigates the user automatically. Rewrote the section to state the
   composable is claim-generic (not Support-Committee-specific) and
   that recovery is manual/inline, not an automatic redirect.

Also corrected the Step 4B completion record's own "zero matches,
nothing to itemize" self-review claim (see Checks performed, above) and
its file-accounting wording (see Files changed this step, above).

**Verification for this correction pass:** re-checked all 6 claims
directly against `frontend/package.json`, `frontend/app/pages/`,
`app/middleware/role.ts`, `app/middleware/screen.ts`,
`app/constants/workflow.ts`, `app/constants/role-surfaces.ts`,
`app/composables/useScreenPermissions.ts`,
`app/composables/useEngineClaim.ts`, `app/types/models.ts`, and
`app/pages/workflows/instances/[id].vue` before editing. Ran Prettier
`--check` on `docs/frontend-guide.md` (the only Markdown file this
correction pass touched besides this plan document) and confirmed
stability on rerun. Re-ran the link/anchor checker — zero broken links.
Re-reviewed every voting/retired-status/customs match in the corrected
document and confirmed each is confined to an intentional context
(verification banner, route-removal statement, the Executive Voting
residue section, or the "what this document removes" itemization).
Confirmed `git status` reports exactly 2 modified tracked files and 12
untracked files, matching the unchanged, pre-existing baseline.

**Blockers:** none.

**Step 4B follow-up correction (2026-07-13) — endpoint accuracy.** An
independent review caught one remaining inaccuracy in the
route-admission section: it cited the screen-permissions hydration
source as `/auth/me`. Read `frontend/app/stores/auth.store.ts`
directly: `fetchUser()`/`extendSession()` call
`$fetch('/api/auth/me', ...)`; confirmed against
`backend/routes/api.php`'s `Route::get('me', [AuthController::class,
'me'])` registered under the `api/auth/` prefix group — the real path
is `/api/auth/me`, not `/auth/me`. Corrected the path in
`docs/frontend-guide.md` and rewrote the hydration description to be
phase-precise rather than reusing the imprecise "server-hydrated" term
from the prior correction round: `screen.ts` explicitly skips
enforcement during server execution
(`if (import.meta.server) return`); `auth.screenPermissions` is
populated client-side, from a backend-provided response
(`store.screenPermissions = data.screen_permissions ?? {}` inside
`applyAuthMe()`); `useScreenPermissions().can()` then reads that
client-hydrated store field. Replaced this same "server-hydrated"
wording above with "backend-provided, client-hydrated." Re-ran
Prettier `--check` on both corrected files and all 6 extant Step 4B
files, re-validated links/anchors (zero broken), and reconfirmed the
2-modified/12-untracked baseline.

**Blockers:** none.

**Step 4B — ✅ APPROVED.** Independent verification of commit
`64e8ba52` confirmed: `/api/auth/me` matches the frontend request and
backend contract, client-side hydration and the server-skip behavior
match source, both commits are signed with the required co-author,
Prettier passes across all 6 extant files, internal links resolve, the
working tree matches the 2-modified/12-untracked baseline, and the
corrected files have no post-commit diff.

**Step 4C — ✅ DONE (2026-07-13).** Scope: only
`docs/05-backend-guide.md` → `docs/backend-guide.md`. This is the last
sub-step of Step 4 — after this, Step 4 as a whole is complete.

Pre-flight `git status` confirmed the baseline (2 modified tracked
files, 12 untracked files) before touching anything; confirmed
unchanged post-flight.

**Source verification performed.** Read `docs/05-backend-guide.md` in
full (920 lines) before deletion. Verified directly against current
backend source rather than inheriting any legacy claim: `routes/api.php`
(confirmed no `/api/voting`, `/api/customs`, `/api/support-review`, or
`/api/workflow` top-level prefix groups exist — only `Route::prefix('auth')`
unversioned and `Route::prefix('v1')` versioned; confirmed
`config('demo.allowed_environments')` gates demo/switch-role routes,
matching `api-reference.md`'s existing environment-dependent caveat);
`EngineClaimService::ttlMinutes()` (admin-setting-backed, not Redis);
`AuthController` (login throttle `5,1`, session `regenerate()` on
login, `logFailedLogin()`'s `null` actor); `AuthSecuritySettings` +
`config/auth_security.php` (lockout is admin-configurable via
`SettingResolver`, config defaults 5 attempts / 15 minutes);
`AppServiceProvider::registerApiRateLimiter()` (`api-default`, 120/min
default, user-or-IP keyed); `UploadDocumentRequest`/`UploadSwiftRequest`/
`FxConfirmationUploadRequest` (PDF-only via `mimetypes:application/pdf`);
`EngineRequest` (confirmed no `setAttribute()` override) and
`DirectStatusMutationException` (confirmed exists, never thrown —
re-verified rather than trusting the legacy file's already-correct
claim on this point); `DataScope`/`DataScopeContext`
(`app/Services/Authorization/`); `App\Support\ApiResponse` (the general
response envelope shape).

**Inaccuracy found and corrected, not inherited:** AGENTS.md's Security
Baseline states "Account lockout: after 10 consecutive failures
(15-minute lockout)." Read `AuthSecuritySettings::lockoutAttempts()`/
`lockoutDurationMinutes()` and `config/auth_security.php` directly:
lockout is admin-configurable, not a fixed constant, and the **config
defaults are 5 attempts / 15 minutes**, not 10 attempts. The new
document states the verified default and the admin-configurable nature
explicitly, rather than repeating the "10 consecutive failures" figure
found in AGENTS.md. (This is a documentation-accuracy correction only —
AGENTS.md itself was out of scope for this step and was not edited;
flagging it here as a known open discrepancy for whoever next touches
AGENTS.md's Security Baseline section.)

**Live-code-vs-dead-code distinction (the specific risk this step's
instructions flagged for `Customs`-named code).** Searched for
`Voting`-named and `Customs`-named backend surface separately and
verified each individually rather than applying one blanket
"legacy naming" treatment:

- `Voting`-named code (`app/DTOs/Voting/VotingTally.php`,
  `VoteResource.php`, `VotingTallyResource.php`) — confirmed via grep
  that none of these classes are referenced by any controller, route,
  or other service; `routes/api.php` has zero routes containing
  "voting"/"vote". Genuinely dead code.
- `Customs`-named code (`CustomsDeclaration` model, `CustomsException`,
  `CustomsDeclarationPolicy`, `EngineCustomsService`,
  `CustomsDeclarationGenerator`, `CustomsFxPdfEffect`) — confirmed via
  grep that `EngineCustomsService` and `CustomsFxPdfEffect` are
  referenced by `EngineFxConfirmationController` and registered in
  `AppServiceProvider`; the `customs_declarations` table is a live,
  actively-written table backing the FX confirmation flow; two
  `EngineFxConfirmationController` endpoints use the URL segment
  `customs-declaration`. This is **live code under legacy pre-rename
  naming**, not dead code — the new document says so explicitly and
  instructs against describing it as removal-eligible, while still
  directing new copy/code toward "external FX confirmation" language.

**Preserved:** service-boundary framing (no monolithic
Workflow/Voting Service); `WorkflowVersionState::isEditable()`
immutability (linked to `architecture/02-workflow-engine.md`, not
duplicated); the `EngineTransitionService::execute()`-is-the-only-path
rule with its exact `setAttribute()`/`DirectStatusMutationException`
findings, carried forward from the legacy file's already-correct
material rather than silently dropped; `DataScope`-based
organization/data-scope enforcement (linked to
`architecture/03-permission-model.md`); authentication protections
(Sanctum, session regeneration); audit-logging behavior (`AuditService`,
`workflow_history`, `role`-in-audit-entry rule); PDF-only file
validation.

**Removed:** the fixed 18-value status vocabulary and "Editable
States"/"Locked States" framing; `support_claimed_by`/
`support_claimed_at`/`current_status` as live field names; the "Voting
Service" section and "Voting Rules"/"Voting Session Rules"/"Voting
Restrictions" as live functionality; the "Suggested API Structure"
listing `/api/workflow`/`/api/support-review`/`/api/voting`/`/api/customs`
as top-level route groups (verified none exist; the document now
points to `api-reference.md`/`route:list` instead of a second
manually-duplicated route list); fixed per-role "Visibility Scope By
Role" sections as the access-control model (now linked to
`architecture/03-permission-model.md`); the Redis-based-TTL-key claim
description (the live TTL source is the admin setting); the
unqualified "10 consecutive failures" lockout figure.

**Migration.** Tested rename detection the same way as Steps 4A/4B —
`git diff --cached --stat -M50%` after `git rm` + `git add` did not
detect a rename (920 deletions / 313 insertions, too dissimilar), so
delete+add is the correct representation.

**Link updates** — every live reference to the old path repointed:
`docs/README.md` (Backend guide row: planned → **live**),
`docs/architecture/README.md` (dropped the backend-guide "not yet
migrated" pointer since it's now live; the note now cites Step 6, not
Step 4C, since `docs/00-project-brief.md` is the only remaining
not-yet-migrated file this note was tracking), `AGENTS.md` (minimal
path-only correction), `backend/CLAUDE.md` (Docs Reference entry
repointed). The `docs/superpowers/` match is a historical planning
artifact, correctly left untouched, same treatment as Steps 4A/4B.

**Checks performed.** Self-reviewed the new document against the 5
forbidden-content categories (active-voting claims, retired status
vocabulary, stale route families, legacy claim-field names,
customs-facing business terminology) via targeted `grep` — every match
found was reviewed individually and confined to an intentional context
(verification banner, the "what this document removes" itemization, or
a disclosed live code-identifier, never business-facing prose); zero
customs-facing business-terminology leaks. Ran Prettier on every
touched Markdown file individually (root-scoped files from repo root;
confirmed `backend/CLAUDE.md` has no `backend/`-local Prettier config
overriding root defaults, unlike `frontend/`) and confirmed `--check`
stability on rerun. Read the formatted files back for line-leading
`+`/`-` artifacts — none found. Ran the link/anchor checker across all
touched files — zero broken links.

**Deviations:** none.

**Blockers:** none.

**Files changed this step:** commit `02d7ee2f` changed 7 paths —
`docs/05-backend-guide.md` (deleted), `docs/backend-guide.md`
(created), 4 files with link/content corrections (`docs/README.md`,
`docs/architecture/README.md`, `AGENTS.md`, `backend/CLAUDE.md`), and
this plan document itself (the prior version of this record omitted
the plan document from its own accounting — corrected here). After the
deletion, 6 files touched by this step remain on disk and are eligible
for formatting/link checks. A separate correction commit, `85963bb0`,
later fixed a pre-commit-hook formatting-drift bug in `02d7ee2f` and
changed only `docs/backend-guide.md`. No production frontend or
backend code was changed.

**Step 4C accuracy correction (2026-07-13).** A focused review caught 6
inaccuracies in `docs/backend-guide.md`, all re-verified directly
against current backend source before correcting:

1. **Route topology was understated as two surfaces.** The document
   described `routes/api.php` as unversioned `auth/` plus versioned
   `v1/` only. Read `routes/api.php` directly: it also registers a
   public unversioned `GET /api/settings/public` and two
   `auth:sanctum`-protected unversioned groups covering `profile/*`,
   `settings/*`, `financing/utilization`, `admin/*` (health, settings,
   notification templates — distinct from `v1`-prefixed admin
   endpoints), `search/*`, and `dashboard/*`. Rewrote the section
   around three surfaces (`auth/`, `v1/`, additional unversioned
   groups) and added the explicit note that a controller's `Api\V1`
   PHP namespace does not imply a `/v1` URL prefix — the two are
   independent, verify the actual `Route::prefix()` nesting.
2. **Authentication was described as cookie-only with unconditional
   session regeneration.** Read `AuthController::issueSession()`
   directly: it branches on `$request->hasSession()` — cookie mode
   logs into the `web` guard and regenerates the session; token mode
   creates a Sanctum personal access token and returns it as a Bearer
   token, with no guard/session involvement. Read `logout()` directly:
   it always revokes the current personal access token when present,
   but only invalidates the session and regenerates the CSRF token
   inside `if ($request->hasSession())`. Rewrote the Authentication
   section to describe both modes and the conditional logout behavior.
3. **The "`execute()` is the only path" rule was stated as absolute.**
   Read `EngineRequestService` directly: it sets `current_stage_id`/
   `status` directly during request creation, outside `execute()`.
   Read `EngineTransitionService::abandonDraft()` directly: it sets
   `status: ABANDONED` directly, without calling `execute()`. Neither
   contradicts the prohibition on ad hoc mutation — both are explicit,
   narrower service-managed lifecycle operations distinct from an
   ordinary workflow transition. Rewrote the heading, prose, and code
   comments to state the rule precisely: `execute()` is mandatory for
   ordinary transitions; ad hoc mutation from a controller or arbitrary
   service code is still forbidden; `EngineRequestService` (creation)
   and `abandonDraft()` (abandonment) are sanctioned, narrower
   exceptions by design, not gaps in the rule.
4. **Voting-residue description was inaccurate and its heading
   contradictory.** The document said `VotingTally`/`VoteResource`
   were "self-referencing only" and used the heading "live-referenced
   dead code" (contradictory on its face). Read
   `VotingTallyResource.php` directly: it imports and references
   `VotingTally` (`use App\DTOs\Voting\VotingTally;`, typed as its
   `$resource`, reading its properties) — the two classes reference
   each other, not each in isolation. `VoteResource` is a separate,
   standalone class with no such dependency. What actually makes all
   three dead is that none is reachable from any controller or route —
   re-confirmed via a direct grep of `app/Http/Controllers/` and
   `routes/` for all three class names, zero matches. Rewrote the
   section and its heading to describe an isolated, unwired residue
   cluster rather than "self-referencing," and replaced the
   contradictory heading with clear dead-residue wording.
5. **Claim-setting runtime ownership was misattributed.** The document
   attributed `EngineClaimService::ttlMinutes()`'s runtime lookup to
   `AdminSettingsService`. Read `EngineClaimService`'s constructor
   directly: it injects `SettingResolver`, not `AdminSettingsService`.
   `AdminSettingsService` owns the setting's catalog entry (default,
   allowed range) that ends up in the `SystemSetting` row
   `SettingResolver` reads — it is not the runtime dependency
   `ttlMinutes()` calls. Corrected both mentions (the Claim lifecycle
   section and the "what this document removes" itemization) to
   attribute the runtime read to `SettingResolver` and the catalog
   ownership to `AdminSettingsService` separately.
6. **This plan's own Step 4C completion record undercounted its file
   list.** Commit `02d7ee2f` changed 7 paths, including this plan
   document itself; the prior record's "4 files... 6 total" omitted
   the plan file from the accounting sentence even though the "6
   total" figure implicitly included it. Corrected above (Files changed
   this step) to state the 7/6 split explicitly, matching the pattern
   used in the Step 4A/4B correction records.

**Verification for this correction pass:** re-checked all 6 claims
directly against `routes/api.php`, `AuthController.php`
(`issueSession()`/`logout()`), `EngineRequestService.php`,
`EngineTransitionService.php` (`abandonDraft()`),
`VotingTallyResource.php`, `VoteResource.php`, `EngineClaimService.php`'s
constructor, and `AdminSettingsService.php` before editing. Ran
`php artisan route:list --path=api` to re-confirm the three-surface
route topology against the live route table, not just the route
declarations. Ran Prettier on `docs/backend-guide.md` and this plan
document, and re-checked all 6 extant Step 4C files; confirmed
`--check` stability on rerun. Re-ran the link/anchor checker — zero
broken links. Confirmed `git status` reports exactly 2 modified tracked
files and 12 untracked files, matching the unchanged, pre-existing
baseline. Did not touch AGENTS.md's lockout-figure discrepancy, per
instruction — it remains deferred to Step 9.

**Blockers:** none.

**Step 4C accuracy correction, follow-up (2026-07-13).** A further
review caught 2 residual overstatements in the just-corrected
`docs/backend-guide.md`, both re-verified directly against current
backend source:

1. **Voting-residue dependency direction was still stated as
   symmetric.** The prior correction round said `VotingTallyResource`
   and `VotingTally` "reference each other." Read `VotingTally.php`
   directly: it is a plain DTO with zero imports and no reference back
   to `VotingTallyResource` — the dependency is one-way,
   `VotingTallyResource` → `VotingTally`. The conclusion that the
   cluster is unreachable from any controller/route is unaffected and
   remains correct. Corrected the wording to state the one-way
   direction explicitly rather than "reference each other."
2. **The state-mutation rule was still effectively absolute.** The
   prior correction round scoped the exceptions to
   `EngineRequestService` (creation) and `abandonDraft()` (abandonment)
   but still implied no other code anywhere writes
   `current_stage_id`/`status` directly. Read
   `backend/app/Console/Commands/PerfLoadScenarioCommand.php` directly:
   it bulk-inserts both columns via a chunked raw `insert()` (not
   Eloquent, not `execute()`) to generate synthetic
   `engine_requests` rows for performance-load testing — a real
   counterexample to an absolute reading of the rule. Rewrote the
   section to scope the prohibition explicitly to **production
   request-handling paths** (controllers, services, jobs, listeners
   acting on live data), and added synthetic performance/test/seed
   fixture generation as a distinct, non-production category that is
   allowed to bypass the engine's mutation paths precisely because it
   never touches a live request — while explicitly stating this is not
   a supported production mutation pattern to imitate.

**Verification for this follow-up:** re-checked both claims directly
against `VotingTally.php` and `PerfLoadScenarioCommand.php` before
editing. Ran Prettier on `docs/backend-guide.md` and this plan
document, and re-checked all 6 extant Step 4C files; confirmed
`--check` stability on rerun. Re-ran the link/anchor checker — zero
broken links. Confirmed `git status` reports exactly 2 modified tracked
files and 12 untracked files, matching the unchanged, pre-existing
baseline. Did not touch AGENTS.md, per instruction.

**Blockers:** none.

Step 4 (all three sub-steps — 4A, 4B, 4C) is now complete.

Holding for review before Step 5, per instruction.

**Step 5 — Extract the 3 UX patterns from `docs/user-view/` into
`docs/frontend-guide.md`** (density tiers, forbidden-actions table,
cross-role handoffs pattern) as generic templates — this happens before
archiving so the extraction has the source material still in its original
location for reference during the extraction, not after.

**Step 5 — ✅ DONE (2026-07-13).** Scope: extract and genericize the 3
UX patterns named above; `docs/user-view/` itself is read-only source
material for this step — not archived, not modified.

Pre-flight `git status` confirmed the baseline (2 modified tracked
files, 12 untracked files) before touching anything; confirmed
unchanged post-flight.

**Source read.** Read `docs/user-view/data-entry.md` (665 lines) and
`docs/user-view/support-committee.md` (448 lines) in full — one
operational/task-oriented role and one claim-based CBY-global role, the
two most structurally distinct posture types in the set — to confirm
the pattern shape is consistent before genericizing. Spot-read
`docs/user-view/bank-admin.md`'s "Operational Posture" table (a
governance/administrative posture, the third tier) to confirm the
`Aspect | Tone` table shape holds across all three density tiers.
**Correction (2026-07-13, from an independent review):** this record
originally claimed all 8 role files under `docs/user-view/` contain all
three named sections. Direct heading counts
(`grep -c "^## <heading>$"` per file) show that is not quite right: 7
of the 8 files (`bank-admin`, `bank-reviewer`, `committee-director`,
`data-entry`, `executive-member`, `support-committee`,
`swift-officer`) contain all three exact section headings
("Operational Posture," "Forbidden Actions Reference," "Cross-Role
Handoffs"). `cby-admin.md` contains only "Forbidden Actions
Reference" — it has no exact "Operational Posture" or "Cross-Role
Handoffs" heading. This does not weaken the extraction rationale: the
patterns still recur broadly across the set (7 of 8 files have all
three; the two full reads plus one spot-read remain representative of
that broad recurrence), but the original "all 8 files, all three
patterns" claim was imprecise and is corrected here rather than left
standing.

**Extraction, not preservation.** All three patterns were rewritten as
generic authoring templates, not copied role/status content:

1. **Per-surface operational-posture template** — a new subsection
   under `docs/frontend-guide.md`'s existing "Operational density
   composition" (added in Step 4B), since a role-posture-tier mapping
   table already lived there; the new content is a fill-in-the-blank
   `Aspect | What to fill in` template (Work mode, Primary/Secondary/
   Tertiary surface, State language, Visual density, Decision/feedback
   tone) rather than a second, redundant density-tier table. The "State
   language" row explicitly directs authors to the four canonical
   concepts (`runtime_status`/`current_stage`/
   `current_stage.semantic_role`/`final_outcome`) instead of a
   per-role simplified-status mapping sourced from the retired
   `docs/user-view/` vocabulary — the opposite of what the source
   material did (`data-entry.md`'s "Status Presentation" table alone
   maps 22 unique legacy enum values to 6 simplified Arabic labels).
2. **Forbidden-actions template** — a new "Forbidden-actions template"
   section: a numbered authoring checklist (enumerate the real
   authority boundary from `stage_permissions`/screen capabilities, not
   a hardcoded per-role table; state as non-rendering, not disabled
   controls; cross-reference backend enforcement; do not enumerate
   voting actions as a forbidden category, since Executive Voting is
   out of V1 and there is no vote-casting action to forbid in the first
   place) — not a copy of any role's specific forbidden-actions list
   (which named concrete removed/retired actions like "Cast or modify
   an executive vote").
3. **Cross-role handoff template** — a new "Cross-role handoff
   template" section: a numbered authoring checklist keyed on stage
   transitions and semantic roles ("stage A → stage B via transition
   T"), explicitly rejecting the source material's fixed-status-jump
   framing (`data-entry.md`'s handoffs were phrased as
   `SUBMITTED`→`BANK_REVIEW`-style status jumps) and its
   fixed-handoff-count framing (`support-committee.md` enumerated
   "three handoff directions" as if that count were a property of the
   role rather than of one specific published workflow version's stage
   graph).

**Removed/rejected during extraction (verified against current
architecture, not carried forward):** the `docs/user-view/` status
vocabulary (22 unique legacy enum values in `data-entry.md`'s mapping
alone — distinct from the genuinely-separate 18-value vocabulary of
the older legacy core documents) used throughout the source
density/status-language rows; the
"CorrectionBanner"/"ActiveReviewBanner" naming as if these are the only
possible banner components (kept generic: "a banner on the
request-detail page"); fixed per-role dashboard framing (the source
material predates the two-family capability-led dashboard model from
[`../architecture/04-dashboard-architecture.md`](../architecture/04-dashboard-architecture.md));
customs-facing terminology (none was present in the specific
density/forbidden-actions/handoff sections read, so none needed
removal, but the templates themselves direct new authors toward
external FX confirmation terminology per
[`AGENTS.md`](../../AGENTS.md)'s canonical rule); static per-role
workflow-path framing (`support-committee.md`'s handoff list assumed a
single fixed pipeline `BANK_REVIEWER → SUPPORT_COMMITTEE →
EXECUTIVE_MEMBER`, which the template replaces with Designer-defined,
workflow-version-specific stage transitions).

**`docs/user-view/` disposition: unchanged, per instruction.** No file
under `docs/user-view/` was modified, moved, or archived during this
step — it remains exactly as it was, still governed by the existing
"deprecated historical material" framing in `AGENTS.md` and
`docs/frontend-guide.md`'s "Per-Role UX Authority" section (unchanged
from Step 4B). Its eventual archival disposition (per §5/§10 of this
plan) is out of scope for Step 5.

**Checks performed.** Self-reviewed the new template sections via
targeted `grep` for the 5 forbidden-content categories named in the
instructions (22-value status vocabulary, active Executive Voting
claims, fixed per-role dashboards, customs-facing terminology, static
workflow paths) — zero live-content matches; the one "vote-casting"
match found is inside an explicit negative statement inside the
forbidden-actions template itself ("there is no vote-casting... action
to list"), correctly stating Voting is out of V1, not reintroducing it.
Confirmed via `git status` that `docs/user-view/` has zero pending
changes. Caught and fixed one cross-reference inaccuracy during
self-review: a template point cited "Never Trust Frontend Permissions"
as an existing heading/bullet in this document — the actual bullet
text is "Frontend permissions are UX only" under "Architecture rules,"
and that section appears **below**, not above, the new template
sections in document order; corrected both the quoted text and the
"above"/"below" direction. Ran Prettier on `docs/frontend-guide.md` and
this plan document; confirmed `--check` stability on rerun. Read the
formatted files back for line-leading `+`/`-` artifacts — none found.
Ran the link/anchor checker across both touched files — zero broken
links.

**Deviations:** none.

**Blockers:** none.

**Step 5 accuracy correction (2026-07-13).** An independent review
caught 4 inaccuracy groups in the Step 5 additions, all re-verified
directly before correcting:

1. **Pattern prevalence overstated** — corrected above (see the
   inserted correction note): 7 of 8 `docs/user-view/` files contain
   all three named sections; `cby-admin.md` has only "Forbidden
   Actions Reference." The extraction rationale is unaffected — the
   patterns still recur broadly.
2. **Wrong enum count for the DATA_ENTRY mapping** — the Step 5
   prose and the new `docs/frontend-guide.md` template text both said
   `data-entry.md`'s "Status Presentation" table maps "18 raw enum
   values." Direct extraction of every backtick-quoted enum value from
   that table (`grep -oE` for the pattern, deduplicated) counts **22
   unique values**, not 18. Corrected both files. Per instruction, this
   correction is scoped narrowly to statements specifically describing
   `docs/user-view/`/the DATA_ENTRY mapping — the many other "18-value"
   references throughout this plan describing the separate, older
   legacy core documents (`docs/01-workflow-and-business-rules.md`,
   `docs/03-database-and-models.md`) are untouched, since those
   documents genuinely used an 18-value vocabulary distinct from
   `docs/user-view/`'s 22-value one.
3. **Four-concept model stated incompletely** — the operational-posture
   template's "State language" row and prose named only
   `runtime_status`/`current_stage`/`final_outcome`, omitting
   `current_stage.semantic_role`. Added it explicitly in both the table
   row and the prose in `docs/frontend-guide.md`, while preserving the
   distinction that a surface may display only a relevant subset for
   its audience — it is state-language **decisions** that must use all
   four concepts, not that every surface must render all four.
4. **Handoff template required a non-nullable semantic role** — the
   template said to state a handoff as "stage A (semantic role X) →
   stage B (semantic role Y)," implying every stage always has one.
   Read `database/migrations/2026_07_06_000006_wp4_semantic_columns.php`
   directly: `semantic_role` is declared
   `->string('semantic_role', 50)->nullable()`. Rewrote the template to
   key handoff identity on stage code/name and transition code first,
   treat `semantic_role` as optional supporting context (present,
   resolvable through the `SemanticRegistry` code-alias fallback, or
   explicitly absent), and added an explicit instruction never to
   invent a semantic role from a stage's display label.

**Verification for this correction pass:** re-checked all 4 claims
directly — heading counts via `grep -c` per `docs/user-view/*.md` file,
the enum count via a deduplicated `grep -oE` extraction from
`data-entry.md`'s Status Presentation table, and the nullable
`semantic_role` column via the migration file — before editing. Ran
Prettier on `docs/frontend-guide.md` and this plan document; confirmed
`--check` stability on rerun. Re-ran the link/anchor checker across
both files — zero broken links. Re-reviewed the corrected template
sections for the same 5 forbidden-content categories as the original
Step 5 self-review — zero live-content leaks. Confirmed via `git
status` that `docs/user-view/` remains untouched and the pre-existing
2-modified/12-untracked baseline is unchanged.

**Blockers:** none.

**Files changed this step:** `docs/frontend-guide.md` (extended with 3
new template sections) and this plan document — 2 files. No file under
`docs/user-view/` was touched. No production frontend or backend code
was changed.

**Step 5 action-availability correction (2026-07-13).** Outcome: the
forbidden-actions template's "never disabled, always non-rendered"
rule collapsed two distinct backend states into one. Files:
`docs/frontend-guide.md` only. Finding: `STAGE_EXECUTION_FORBIDDEN`
(no `stage_permissions` grant) is genuinely non-rendered, but
`CLAIM_NOT_HELD` (permission held, stage requires an unheld claim) is
not the same — verified against the shipped request-detail page
(`frontend/app/pages/workflows/instances/[id].vue`), whose
`claimRequiredButNotHeld` computed state renders a visible "claim to
continue" prompt, not a hidden control. Rewrote template point 2 to
distinguish the two states explicitly. Verification: Prettier
`--check` on the touched file, link/anchor check, `git status`
confirms baseline unchanged.

From this step forward, plan records for this document follow a
lighter format (outcome, files, material findings, verification,
deviations) per updated process — full narrative detail is no longer
required for routine steps.

Holding for review before Step 7, per instruction (proceeding directly
into Step 6 without a review checkpoint).

**Step 6 — Merge `docs/00-project-brief.md`'s framing into
`docs/README.md`**, then move the rest of `docs/00` to
`docs/archive/project-brief-2026-05.md`.

**Step 6 — ✅ DONE (2026-07-13).** Outcome: merged still-relevant
framing (platform purpose, actor org types) into `docs/README.md`'s
new "Project purpose" section, pointing to `frontend/PRODUCT.md` for
the role table rather than duplicating it; archived the rest verbatim
via `git mv` (detected as a clean rename) to
`docs/archive/project-brief-2026-05.md` with a banner. Files:
`docs/README.md` (new section), `docs/architecture/README.md` (link
repointed), `docs/00-project-brief.md` → `docs/archive/project-brief-2026-05.md`
(moved + banner). Material findings: the source brief's per-stage
lifecycle detail, fixed status vocabulary, and Executive Voting framing
are all superseded by `architecture/02-workflow-engine.md` and were not
carried into the merge — only the org-level purpose/actor framing was
still accurate and non-duplicative. Verification: Prettier `--check` on
touched files, link/anchor check, `git status` confirms baseline
unchanged. Deviations: none.

Holding for review before Step 7, per instruction.

**Step 7 — Merge `operations/runbook.md`, `operations/retention-policy.md`,
and `audit-functional/21`'s checklists into `docs/production-guide.md`.**
Do NOT delete the two operations source files in this step — leave them in
place with a "superseded by docs/production-guide.md" banner for one
release cycle before removing, in case any external tooling/bookmark
references their exact path.

**Step 5 wording fix, bundled into Step 7's commit per instruction.**
`docs/frontend-guide.md`'s operational-posture template overstated the
four state concepts as determining "which action set is available."
Corrected: state presentation/classification uses the four concepts;
action availability is a separate concern driven by `can_execute`,
Designer-defined outgoing transitions, claim state, and backend
validation.

**Step 7 — ✅ DONE (2026-07-13).** Outcome: created
`docs/production-guide.md`, merging `operations/runbook.md` +
`operations/retention-policy.md` + `21-audit-closure-report.md`'s §13
(deployment checklist) and §14 (rollback/incident checklist). Source
files left untouched (no banner added this round — the user's Step 7
scope explicitly said not to move/delete them and did not ask for a
banner edit; that remains for the later archival step per §5/§9 of
this plan). Files: `docs/production-guide.md` (new),
`docs/README.md` (row → live), `docs/frontend-guide.md` (Step 5
wording fix, bundled). Material findings from verification against
`backend/routes/{api,console}.php` and `backend/config/{retention,demo}.php`:
(1) a 9th scheduled command, `workflow-history:archive-old` at daily
03:10 with its own `workflow_history_hot_months`/
`workflow_history_archive_batch_size` config pair, exists in neither
source file — added; (2) the runbook's `GET /api/v1/admin/health` is
wrong — `admin/health` is registered unversioned
(`GET /api/admin/health`) — corrected. All other commands, schedules,
and config keys in the source files matched current source exactly.
Verification: Prettier `--check` on touched files, link/anchor check,
`git status` confirms baseline unchanged, committed blob verified
immediately post-commit. Deviations: none.

Holding for review before Step 8, per instruction.

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

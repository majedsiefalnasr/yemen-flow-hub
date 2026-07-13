# Documentation Archive

This directory holds documents that are historically valuable but no
longer serve as the live, "read this to understand the system today"
reference — audit trails, closed audit reports, and pre-dynamic-engine
design/UX/test material. Nothing here is deleted; everything is preserved
in full, moved verbatim, with a banner explaining why it was archived.

The documentation consolidation migration that produced this tree is
complete; see
[`audit-functional/22-documentation-consolidation-plan.md`](audit-functional/22-documentation-consolidation-plan.md)
for the full plan and its closing record (§14).

Moved contents:

- [`audit-functional/`](audit-functional/) — the full 23-file record of
  this project's 6-phase RBAC/workflow audit (Phases A–F, files `00`–`21`)
  plus the consolidation plan itself
  (`22-documentation-consolidation-plan.md`), archived here on completion
  (Step 14) after driving the migration from a live location.
- [`audit/`](audit/) — the earlier, separate performance/scalability audit
  (scope, architecture, database/API/frontend/security findings, load-test
  plan, roadmap, and its `evidence/` subtree of per-finding verification
  notes and `EXPLAIN` output).
- `project-brief-2026-05.md` — the original project brief, moved once its
  still-current framing language was extracted into `docs/README.md`
  (Step 6).
- [`user-view/`](user-view/) — the 8 per-role UX specs that predate the
  dynamic workflow engine (fixed-role dashboards, the removed 22-value
  status enum, voting UI). Gate approved per the Phase F closure report
  ([`audit-functional/19-phase-f-inventory.md`](audit-functional/19-phase-f-inventory.md)
  §11); archived (Step 10).
- [`testing-manual/`](testing-manual/) — the 9-file manual QA test suite
  (root-level `testing-manual/` before this move) that predates the
  dynamic workflow engine — fixed status pipeline, executive voting flow.
  Retained for structural scaffolding only, not current test authority;
  replaced by the live [`docs/testing-guide.md`](../testing-guide.md)
  (Step 12).

# Documentation Archive — Migration In Progress

This directory will hold documents that are historically valuable but no
longer serve as the live, "read this to understand the system today"
reference — audit trails, closed audit reports, and pre-dynamic-engine
design/UX/test material. Nothing here is deleted; everything is preserved
in full, moved verbatim, with a banner explaining why it was archived.

Content migration is in progress; see
[`docs/audit-functional/22-documentation-consolidation-plan.md`](../audit-functional/22-documentation-consolidation-plan.md)
for the full plan, including which directories will land here and in what
order (§5 "Files to archive", §9 the step-by-step migration sequence).

Planned contents (not yet moved):

- `audit-functional/` — the 22-file record of this project's 6-phase
  RBAC/workflow audit (Phases A–F, files `00`–`21`). This consolidation
  plan itself (`22-documentation-consolidation-plan.md`) is a 23rd file in
  the same directory but is not part of that audit trail — it stays live
  in `docs/audit-functional/` until the migration it describes completes,
  rather than moving here with the rest.
- `audit-performance/` — the closed, separate performance/scalability audit.
- `project-brief-2026-05.md` — the original project brief, once its
  still-current framing language is extracted into `docs/README.md`.
- `user-view/` — the 8 per-role UX specs that predate the dynamic workflow
  engine. **Archival of this directory remains gated** per the Phase F
  closure report (`docs/audit-functional/19-phase-f-inventory.md` §11) and
  will not proceed without its own separate approval.
- `testing-manual/` — the 9-file manual QA test suite that predates the
  dynamic workflow engine, approved for archival
  (`docs/audit-functional/22-documentation-consolidation-plan.md` §10),
  replaced going forward by `docs/testing-guide.md`.

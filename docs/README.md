# Yemen Flow Hub — Documentation

Yemen Flow Hub is an internal government banking regulatory workflow
platform for the Central Bank of Yemen (CBY). It is not a public SaaS
product — it's an enterprise-grade, audit-sensitive, workflow-driven
institutional platform.

This index is the starting point for understanding **how the system works
today**. Documentation here describes the current architecture, not the
history of how it was reached — for that history, see
[`archive/`](archive/README.md).

For AI-tool-specific instructions (Claude Code, Cursor, Copilot, Codex),
see `AGENTS.md` at the repository root — it loads this tree as its
documentation authority.

---

## Start here

| Topic                        | Document                                                                                 | Status                                                                                                                                                                         |
| ---------------------------- | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| System architecture          | [`architecture/01-system-architecture.md`](architecture/01-system-architecture.md)       | **live**                                                                                                                                                                       |
| Workflow engine              | [`architecture/02-workflow-engine.md`](architecture/02-workflow-engine.md)               | **live**                                                                                                                                                                       |
| Permission model             | [`architecture/03-permission-model.md`](architecture/03-permission-model.md)             | **live**                                                                                                                                                                       |
| Dashboard architecture       | [`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md) | **live**                                                                                                                                                                       |
| Request state model          | [`architecture/05-request-state-model.md`](architecture/05-request-state-model.md)       | **live**                                                                                                                                                                       |
| Database and models          | [`architecture/06-database-and-models.md`](architecture/06-database-and-models.md)       | **live**                                                                                                                                                                       |
| Extension guide              | [`engine/extension-guide.md`](engine/extension-guide.md)                                 | **live**                                                                                                                                                                       |
| Dynamic vs. fixed philosophy | [`engine/dynamic-vs-fixed.md`](engine/dynamic-vs-fixed.md)                               | **live**                                                                                                                                                                       |
| Frontend guide               | `frontend-guide.md`                                                                      | planned — not yet written (Step 4); current authority is [`04-frontend-guide.md`](04-frontend-guide.md)                                                                        |
| Backend guide                | `backend-guide.md`                                                                       | planned — not yet written (Step 4); current authority is [`05-backend-guide.md`](05-backend-guide.md)                                                                          |
| API reference                | [`api-reference.md`](api-reference.md)                                                   | **live** (partial coverage — see its Coverage status section)                                                                                                                  |
| Auth and account recovery    | `auth-and-recovery.md`                                                                   | planned — not yet moved; current authority is [`07-account-recovery-and-mail.md`](07-account-recovery-and-mail.md)                                                             |
| Development guide            | [`development-guide.md`](development-guide.md)                                           | **live**                                                                                                                                                                       |
| Production guide             | `production-guide.md`                                                                    | planned — not yet written (Step 7); current authority is [`operations/runbook.md`](operations/runbook.md) + [`operations/retention-policy.md`](operations/retention-policy.md) |
| Testing guide                | `testing-guide.md`                                                                       | planned — not yet written (Step 12); current authority is `testing-manual/` (pending archival)                                                                                 |

Rows marked **live** exist today and are authoritative now. Rows marked
_planned_ point at today's real authority in the meantime — do not treat a
_planned_ path as broken; it simply hasn't been created yet by the
documentation consolidation migration (tracked in
[`audit-functional/22-documentation-consolidation-plan.md`](audit-functional/22-documentation-consolidation-plan.md)).

---

## Decision records

- [`decisions/semantic-mapping.md`](decisions/semantic-mapping.md) — the
  `semantic_role`/`SemanticRegistry` mechanism.

## Release notes

- [`release-notes/wp14-legacy-cleanup.md`](release-notes/wp14-legacy-cleanup.md)

## Historical record

- [`archive/README.md`](archive/README.md) — audit trails and pre-dynamic-engine
  material, preserved but no longer live reference.

---

## Canonical facts every document here assumes

These hold across the whole codebase. Full detail lives in the linked
docs above; this is the short version so nothing below has to repeat it:

- **Request state is four separate fields**, never one combined status
  enum: `runtime_status`, `current_stage`, `current_stage.semantic_role`,
  `final_outcome`. The old 22-value `RequestStatus` frontend enum has been
  removed.
- **The Workflow Designer and runtime engine are the source of truth** for
  stages, transitions, permissions, and semantic metadata — not a static
  doc.
- **Executive Voting is out of V1 scope.** No voting UI, voting session
  status, or vote-casting surface exists or should be reintroduced.
- **Dashboards are two families**, with component selection led by
  capability rather than a `role === X` branch: the operational
  `MyWorkDashboard.vue` for every workflow-executor role, and dedicated
  analytics dashboards (`SystemAdminDashboard`/`BankAdminDashboard.vue`)
  for governance/analytics roles whose purpose is fundamentally not
  workflow execution. Route admission to the dashboard page and the
  backend's analytics data dispatch both still gate on a fixed role code
  alongside the capability — see
  [`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md)
  for the exact split between capability-led and fixed-role behavior.
- **Screen capabilities and workflow stage permissions are the primary
  authorization systems** — see
  [`architecture/03-permission-model.md`](architecture/03-permission-model.md).
  A small number of explicit fixed-role guards still exist alongside them
  (e.g. dashboard route admission, the backend's analytics dispatch) and
  must be documented individually rather than generalized away — see
  [`architecture/04-dashboard-architecture.md`](architecture/04-dashboard-architecture.md)
  for the dashboard-specific instance.

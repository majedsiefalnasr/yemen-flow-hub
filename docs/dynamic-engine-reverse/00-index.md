# Dynamic Workflow Engine — Reverse-Engineered Documentation

> **Purpose.** This documentation set reverse-engineers the `dynamic-workflow-engine/`
> codebase (a Lovable-built TanStack Start prototype + its authoritative backend
> handoff specs) so an AI agent can understand the system well enough to **port its
> features and business rules into the Yemen Flow Hub production codebase**
> (Laravel 11 backend + Nuxt 4 frontend).
>
> Every rule here is traced to source. Citations use `path:line` against the
> `dynamic-workflow-engine/` repo unless noted otherwise.

---

## What this system is

A **metadata-driven, configuration-first workflow platform** for the Central Bank of
Yemen / National Committee for Regulating & Financing Imports. Instead of hard-coding
one fixed request lifecycle, an admin **designs** workflows (stages, transitions,
actions, fields, permissions) and the runtime **executes** any published version.

The seeded default workflow is **"تمويل الواردات" (Import Financing)** — an 8-stage
path from bank data-entry to final committee approval (`seed.ts:125`).

There are **two realities** in the repo, and they must not be confused:

| Reality | Where | Status | Authority |
|---|---|---|---|
| **Prototype runtime** | `src/lib/**` (localStorage, pure TS engine, no server) | Shipped, working | UX + engine semantics |
| **Intended backend** | `docs/backend-handoff/*.md` + `openapi.yaml` | Spec only, `Planned` | **Business / security / audit / data rules** |

When the two disagree, **the backend handoff wins** for rules (it explicitly
supersedes prototype shortcuts — e.g. it removes `StageRoutingRule` in favour of
`stage_permissions`, `03-workflow-designer.md:97`). The prototype wins for
**how a screen behaves / looks**.

---

## The 11 rule documents

| # | Doc | Scope | Graph |
|---|---|---|---|
| 01 | [Architecture](01-architecture.md) | Layers, modules, stores, prototype↔backend mapping | Component graph |
| 02 | [App Flow](02-app-flow.md) | Auth → request lifecycle → designer → queue | Flow + stage graph |
| 03 | [Data Flow](03-data-flow.md) | Form → instance.data → history → audit → notifications | Data-flow graph |
| 04 | [Business Rules](04-business-rules.md) | Workflow/version/field/merchant/request/duplicate/SLA | Decision graph |
| 05 | [Security Rules](05-security-rules.md) | JWT/MFA, stage permission matching, scope, concurrency | Authz graph |
| 06 | [Audit Rules](06-audit-rules.md) | Append-only `audit_logs`, events, history linkage | Audit graph |
| 07 | [Notification Rules](07-notification-rules.md) | Events, per-user recipients, audience resolution | Notification graph |
| 08 | [Error Handling Rules](08-error-handling-rules.md) | HTTP + business codes, SSR normalization, no-retry | Error graph |
| 09 | [Logging Rules](09-logging-rules.md) | Audit vs history vs error log, correlation id | Logging graph |
| 10 | [Testing Rules](10-testing-rules.md) | Feature-test gate, stage Definition of Done | Test-gate graph |
| 11 | [Deployment Rules](11-deployment-rules.md) | Nginx/PHP-FPM, queues, scheduler, Redis, backups | Deploy graph |

After the docs: [Phase 2 — Gap Audit](12-gap-audit.md) compares these rules against
the current Yemen Flow Hub codebase and lists what is missing / divergent.

---

## Core glossary

| Term | Meaning | Source |
|---|---|---|
| **Definition** | A *type* of process (e.g. Import Financing). Holds `activeVersionId`. | `types.ts:38` |
| **Version** | An immutable runnable config of a definition. `DRAFT → PUBLISHED → ARCHIVED`. | `types.ts:47`, `03-workflow-designer.md:9` |
| **Stage** | A step in a version. One initial, ≥1 final. Has SLA, order. | `types.ts:55`, `03-workflow-designer.md:36` |
| **Action** | Reusable verb (`APPROVE/REJECT/RETURN/CLOSE/INFO/DRAFT/CUSTOM`). | `types.ts:108`, `seed.ts:89` |
| **Transition** | `from_stage + action → to_stage`. Unique per `(from, action)`. | `types.ts:116`, `07-data-model.md:104` |
| **Stage Permission** | Unified row scoping `org/team/role/user → VIEW|EXECUTE` on a stage. | `03-workflow-designer.md:80` |
| **Field Definition** | A typed input on the request screen (9 types). | `types.ts:164` |
| **Field Rule** | Per-stage `visible/editable/required` override for a field. | `types.ts:182` |
| **Request / Instance** | A live run of a published version. Carries dynamic `data`, `version`. | `types.ts:193`, `04-requests-and-queue.md:4` |
| **Workflow History** | Specialized movement log of a request (stage hops). | `types.ts:204` |
| **Audit Log** | Append-only system-wide event log. | `05-audit-and-reports.md:4` |
| **Queue (طابور دوري)** | A user's actionable requests, *derived* from `current_stage + EXECUTE`. | `04-requests-and-queue.md:42` |

---

## Source-file map (prototype)

```
src/lib/
  workflow-engine/
    types.ts        ← all engine type definitions (the schema)
    engine.ts       ← pure functions: routing, permission match, field rules, mutations
    storage.ts      ← reactive localStorage tables (wfe:* namespace)
    seed.ts         ← default Import Financing workflow + sample requests
    wfAuth.ts       ← engine "current user" (identity switch)
    index.ts        ← barrel re-export
  governance.ts     ← shared admin data: audit, notifications, merchants,
                      reference tables, orgs/teams/roles, permissions matrices
  workflow-bridge.ts← maps legacy account world ↔ engine WfUser; derives
                      requests-screen access; duplicate-invoice check
  db.ts             ← cby.v2.* localStorage cells + version-keyed reset
  mock.ts           ← demo identities, role enum, seed audit/notif/merchant samples
  error-capture.ts  ← out-of-band error recorder
  error-page.ts     ← branded 500 HTML
src/server.ts       ← SSR fetch handler + h3 error normalization
src/components/workflow/
  DynamicForm.tsx, RoleGuard.tsx, ScreenGuard.tsx, RoleSwitcher.tsx,
  OrgProcessStepper.tsx, LockedBanner.tsx
src/routes/         ← TanStack file-based routes (admin.*, requests.*, workflows.*, ...)
docs/backend-handoff/ ← AUTHORITATIVE rule specs (00–09 + data-model + openapi)
```

## How to read this for porting

1. Read **04 (business), 05 (security), 06 (audit)** first — they carry the rules the
   production app must honour.
2. Use **02/03** to understand the runtime mechanics those rules wrap.
3. Use **12 (gap audit)** as the actionable to-port checklist.

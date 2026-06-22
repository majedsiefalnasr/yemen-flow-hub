# Lovable Audit — `dynamic-workflow-engine/` vs current `frontend/`

**Date:** 2026-06-22
**Scope:** Read-only audit. No code changed. Compares the Lovable reference app (`dynamic-workflow-engine/`, React + TanStack Start + bun) against the shipped app (`frontend/`, Nuxt 4 + Vue). Goal: enumerate what Lovable has that the current code lacks, so we can decide what to port and how.

> Stacks differ (React/TanStack vs Nuxt/Vue), so this compares **features, surfaces, and the data/permission model** — not line-for-line code.

---

## 1. Headline finding

The Lovable delta is **not a pile of small features**. It is one fundamental re-architecture plus a finer governance model:

> **Lovable replaced the hardcoded workflow (21-status enum, 8 fixed roles, fixed transitions) with a metadata-driven Dynamic Workflow Engine.** Stages, actions, transitions, permissions, fields, field groups, and routing labels are all **configured at runtime through an admin designer** — not compiled into the code.

In Lovable, a "request" is a **workflow instance** of a published workflow version. The nav proves it: `/workflows` is literally labelled "الطلبات" (Requests). Roles are generic codes (`rc_platform_admin`, `rc_bank_admin`) resolved against org/team/role assignments, not the 8-value `Role` enum.

Everything else current already has — usually **richer** than Lovable (MFA, password reset, email templates, trader module, financing ledger, 8 role dashboards, print/customs flows). So this audit is really about **one big decision**, plus a handful of governance gaps.

---

## 2. Surface-by-surface map

### Present in BOTH (current is equal or richer) — no port needed

| Lovable route | Current page | Note |
|---|---|---|
| `index` (dashboard) | `dashboard.vue` | Current has 8 role-specific dashboards; Lovable has 1 generic. Current richer. |
| `login` | `login.vue` | Current adds MFA/OTP, lockout, demo mode. Current richer. |
| `profile` | `settings/user.vue` | Equivalent. |
| `settings` | `settings.vue` + `settings/{bank,system,user}.vue` | Current richer. |
| `notifications` | `notifications.vue` | Equivalent. |
| `reports` | `reports.vue`, `reports/index.vue` | Current richer (charts, exports). |
| `audit` | `audit.vue` | Equivalent. |
| `merchants` | `merchants.vue` (+ `traders/*`) | See terminology note §4. |
| `requests.*` | `requests/*` | Current static; Lovable instance-based (§3). |
| `customs.$id.print` | `customs/*`, `requests/[id]/print` | Current richer. |
| `bank.users` | `users.vue` / `staff.vue` | Equivalent. |
| `admin.cby-staff` | `admin/cby-staff.vue` | Equivalent. |
| `admin.entities` | `admin/entities.vue` | Equivalent (banks). |
| `admin.roles` | `admin/roles.vue` | Current = display; Lovable = engine-bound (§3). |
| `admin.workflow-docs` | `admin/workflow-docs.vue` | Equivalent. |

### Present in CURRENT only (Lovable does NOT have) — confirms current is ahead elsewhere

`change-temporary-password`, `reset-password`, `mfa-setup`, `maintenance`, `forbidden`/`unauthorized`, `admin/email-templates/*`, `admin/settings`, `banks.vue`, `organization.vue`, `traders/*` (Epic 17), `settings/bank|system|user`, `requests/[id]/customs-preview`, `requests/[id]/print`, plus composables `useFinancingLedger`, `useVoting`, `useTraders`, `useClaimLifecycle`, etc.

### Present in LOVABLE only — **THE GAP** ⬇

| Lovable surface | What it is | Current equivalent |
|---|---|---|
| `admin.workflows` (**61 KB**) | **Visual workflow designer** — definitions/versions, stages, actions, transitions, stage permissions, field groups + fields, per-stage field rules, process graph, validate-before-publish | **NONE** |
| `workflows.index` | Request/instance list driven by the engine (current stage + exec permissions) | partially = `requests/index` (but static) |
| `workflows.instances.$id` | Instance detail/runner: dynamic fields, valid actions, transition with concurrency, history | partially = `requests/[id]` (but static) |
| `admin.teams` | Teams CRUD (team belongs to one org, carries no fixed role) | **NONE** |
| `admin.orgs` | Governance bodies (الجهات) CRUD — protected defaults | **NONE** (`organization.vue` is single-org settings, not CRUD) |
| `admin.screen-permissions` | Screen catalog + capability grants; replaces role-code guards | **NONE** (current uses `RoleGuard`/role middleware) |
| `admin.reference-data` | Reference tables/values admin (supplies dynamic-select options) | **NONE** (`useDocumentTypes` only, no admin) |
| `lib/workflow-engine/*` | The engine itself: `engine.ts`, `types.ts`, `storage.ts`, `seed.ts`, `wfAuth.ts` | **NONE** |
| `workflow/DynamicForm`, `OrgProcessStepper`, `ScreenGuard`, `RoleGuard`, `RoleSwitcher` | Engine-driven form + process view + permission guards | **NONE** (current forms are hand-built per Epic 17) |

---

## 3. The engine data model (what "dynamic" means)

From `src/lib/workflow-engine/types.ts` + `docs/backend-handoff/07-data-model.md`. New entities the current backend/frontend do **not** model:

**Governance:** `organizations`, `teams`, `roles`, `screens`, `screen_permissions`
**Workflow design:** `workflow_definitions`, `workflow_versions`, `workflow_stages`, `workflow_actions`, `workflow_transitions`, `stage_permissions`, `field_groups`, `field_definitions`, `stage_field_rules`
**Runtime:** `requests` (= instances), `request_documents`, `workflow_history`
**Platform:** `reference_tables`, `reference_values`, `notifications`, `notification_recipients`, `report_exports`

Key engine concepts with no current counterpart:
- **Versioning + publish lock** — published versions are immutable; `clone` makes an independent draft; `validate` explains each error; `publish` rejects invalid config.
- **Stage assignments** — who can execute/view a stage by (org → team → role → user), with `viewOnly`.
- **Stage routing rules + stage groups + audiences** — per-audience visibility and per-audience process labels (the "دوري" queue, delivery item #9).
- **Field rule engine** — per-stage `{visible, editable, required}` per field; field types incl. `dynamic_select` sourced from merchants / merchant_companies / reference_data.
- **Generic roles** — `rc_platform_admin`, `rc_bank_admin`, etc., not the fixed 8-role enum.

This directly contradicts several **locked** rules in `AGENTS.md`:
- "Do NOT use statuses not in the canonical enum" — engine has no fixed status enum.
- `WorkflowService::transition()` as the single hardcoded transition path — engine resolves transitions from config.
- Canonical 8-role enum — engine roles are data.

So adopting the engine is **not additive**; it reopens the enum/role/transition contracts that the whole current backend (and Epic 1–17) was built around.

---

## 4. Smaller divergences worth a decision

1. **Merchants vs Traders terminology.** Lovable uses `merchants` / `merchant_owners` / `merchant_companies` throughout. Current (Epic 17) kept `merchant_*` tables but added a **Trader** UI module (`traders/*`, `useTraders`). Need to decide canonical label and whether `traders/*` stays or folds into `merchants`.
2. **Rebrand.** Lovable PRODUCT.md says **"National Committee for Import Financing"** — consistent with the in-flight rebrand (memory: National Committee re-scope). Confirm both apps converge on the same canonical EN/AR name.
3. **One dashboard vs eight.** Lovable has a single generic dashboard; current has 8 role dashboards. If the engine wins, role dashboards may need to become permission/screen-driven instead of role-enum-driven.
4. **Permission model.** Lovable `ScreenGuard` (data-driven screen capabilities) vs current `RoleGuard`/role middleware. Porting screen-permissions means rewiring how every page gates access.
5. **Backend handoff spec exists.** `docs/backend-handoff/` (openapi.yaml + 9 docs + delivery plan, 14 prioritized phases) is a ready-made spec for the Laravel side of the engine. All phases currently `Planned` — Lovable side is **mock/localStorage only**, no real backend yet.

---

## 5. The core question for discussion

Lovable is a **front-end prototype on mock data** (`localStorage`, `seed.ts`) that demonstrates a **dynamic, admin-configurable workflow platform**. Current is a **shipped, tested, backend-integrated app** built on a **fixed workflow** with more operational polish (auth, MFA, email, print, trader/ledger, 8 dashboards, thousands of green tests).

The decision is **not** "port N missing pages." It is:

> **Do we replace the fixed-workflow core with the dynamic engine, or selectively adopt only the governance pieces (teams / orgs / reference-data / screen-permissions) that don't break the enum contracts?**

Three broad paths to weigh together:

- **A — Full engine adoption.** Rebuild requests as workflow instances; config/version the workflow; rewrite backend (`WorkflowService`, status enum, roles) and frontend request flow. Highest value, highest cost, invalidates large parts of Epic 1–17 and the locked AGENTS.md contracts.
- **B — Governance-only port.** Add teams, orgs CRUD, reference-data admin, and screen-permissions on top of the existing fixed workflow. Additive, lower risk, no engine. Leaves the workflow hardcoded.
- **C — Hybrid / phased.** Land governance first (path B), then introduce the engine behind a flag for a single new workflow while keeping the fixed path for existing requests. Highest complexity, lowest disruption per step.

**No code has been changed.** Next step: pick a direction (or a subset of surfaces) and I'll produce a detailed per-item port plan.

---

## 6. Decisions locked (2026-06-22)

The PM mandated the dynamic engine — it is **required, not optional**. Locked direction:

| Decision | Choice | Implication |
|---|---|---|
| Stack | **Stay on Nuxt 4 / Vue.** Lovable = design + contract reference only. | Keep Epic 1–17 frontend, MFA, email, trader/ledger, dashboards. Translate engine logic to Vue/composables. |
| Migration | **Clean start.** Current requests are test data — no migration. | No legacy-instance migration. Path A (clean), not phased-flag C. |
| Backend timing | **Backend-first per phase.** | Each phase: finalize the API contract (migration / model / policy / form requests / resources / feature tests / OpenAPI) **then** build + wire the Nuxt screen. |
| Old workflow core | **Replace the core, keep the infrastructure.** | Remove/retire the fixed `current_status` 21-enum, `WorkflowService::transition`, fixed 8-role enum, Epic 1–17 request flow. Keep auth, MFA, users, audit, email-templates, notifications infra. |
| Default seed | **Adopt Lovable's seed as-is.** | Canonical default = `IMPORT_FINANCING` workflow: 8 stages (CREATE→INTERNAL→SUPPORT→EXEC→FX→FX_CONFIRM→FINAL→CLOSED), 2 orgs, 7 teams, 8 roles, from `src/lib/workflow-engine/seed.ts`. |
| Process | **BMAD epics/stories.** | New **Epic 18: Dynamic Workflow Engine** series, same flow as Epic 1–17. |

### Build order (priorities + dependency fix)

User priority list maps to `backend-handoff` order. One adjustment: **Reference Data (priority 12) foundation is pulled earlier** — workflow-designer `dynamic_select` fields depend on reference tables.

- **Phase 0 — Engine foundation:** port `lib/workflow-engine` (types/engine/storage) to Nuxt composables; new org→team→role identity model; governance + engine + runtime tables + seed; `ScreenGuard`/`RoleGuard` primitives.
- **Phase 1 — Governance (1–5):** orgs · teams · roles · banks · users.
- **Phase 2 — Merchants/Traders (6):** + resolve merchants-vs-traders terminology.
- **Phase 2.5 — Reference-data foundation** (pulled from 12).
- **Phase 3 — Workflow Designer (7):** stages → actions → transitions → stage permissions → field groups → fields → field rules → graph + validate/publish + versioning.
- **Phase 4 — Requests (8) + دوري queue (9):** instances · DynamicForm · concurrency-safe transitions · history · stage-scoped queue.
- **Phase 5 — Audit (10) · Reports (11).**
- **Phase 6 — Reference-data admin UI (12) · Screen-permissions admin (13) · Notifications (14).**

Each phase: Lovable screen = UI reference, `docs/backend-handoff/07-data-model.md` = tables, `08-delivery-plan.md` = acceptance criteria.

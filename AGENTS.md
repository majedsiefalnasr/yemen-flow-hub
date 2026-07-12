# Yemen Flow Hub — AI Single File of Truth

This file is the authoritative reference for all AI tools (Claude Code, Cursor, GitHub Copilot) working across the Yemen Flow Hub mono-repo.

---

## Project Identity

**Name:** Yemen Flow Hub  
**Type:** Internal government banking regulatory workflow platform  
**Client:** Central Bank of Yemen (CBY)  
**Nature:** NOT a public SaaS app. An enterprise-grade, audit-sensitive, workflow-driven institutional platform.

---

## Repository Structure

```
yemen-flow-hub/               ← Root Git repository (git@github.com:majedsiefalnasr/yemen-flow-hub.git)
├── backend/                  ← Laravel 11 API, tracked as normal root files
├── frontend/                 ← Nuxt 4 app, tracked as normal root files
│   ├── PRODUCT.md            ← Product identity, users, roles, operational posture, brand tone
│   ├── DESIGN.md             ← Frontend design token rules, RTL patterns, color token usage
│   ├── SHADCN.md             ← shadcn-vue component reference: recipes, imports, decision table
│   └── CLAUDE.md             ← Frontend AI instructions (loads PRODUCT.md + DESIGN.md + SHADCN.md)
├── docs/                     ← Project documentation (source of truth)
├── DESIGN.md                 ← Root visual design system (typography, spacing, elevation)
└── AGENTS.md                 ← This file
```

---

## Git Workflow

The project uses one Git repository:

| Repo          | Remote                                              | Tracks                                                |
| ------------- | --------------------------------------------------- | ----------------------------------------------------- |
| Root monorepo | `git@github.com:majedsiefalnasr/yemen-flow-hub.git` | Everything: `docs/`, `backend/`, `frontend/`, configs |

`backend/` and `frontend/` are regular directories in the root repository. They are not submodules or nested Git repositories.

### Commit Rules

Each change is committed once in the root repository:

| Change location                                 | Commit to                           |
| ----------------------------------------------- | ----------------------------------- |
| `docs/`, `AGENTS.md`, `DESIGN.md`, root configs | Root repo only (run `git` from `/`) |
| `backend/` code                                 | Root repo only                      |
| `frontend/` code                                | Root repo only                      |

```bash
# From the repository root
git add backend/<files>
git commit -m "feat(workflow): ..."
```

```bash
# From the repository root
git add frontend/<files>
git commit -m "feat(voting): ..."
```

- Commit message format: `type(scope): description` (conventional commits)
- Commit message scope is required. Allowed scopes are `auth`, `backend`, `docs`, `frontend`, `repo`, `settings`, `testing`, `ui`, and `workflow`.
- Commit message type must be a Conventional Commit type such as `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `build`, `ci`, `perf`, or `revert`.
- Examples: `feat(workflow): add support return validation`, `fix(frontend): correct bank queue empty state`, `chore(repo): add lint and format tooling`.
- The repository enforces commit messages with Husky `commit-msg` hooks and Commitlint. Do not bypass hooks with `--no-verify` unless the user explicitly authorizes an emergency exception.
- Co-author all AI-assisted commits with: `Co-Authored-By: Claude <noreply@anthropic.com>`
- All commits must remain signed. Do NOT use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false` as a workaround.
- If signing fails, stop and fix the Git signing setup instead of creating an unsigned commit.
- Never add or commit generated artifacts from `graphify-out/`. Keep them local only, even when they change during agent workflows.

### Quality Gates

Before editing, every agent must run `git -c core.fsmonitor=false status --short` from the root repository. Report existing dirty files before modifying anything, and do not edit dirty files unless they are directly in scope for the current task.

Use the repository quality scripts before committing, but follow the verification ladder below. The project uses `pnpm` for JavaScript tooling; do not migrate to Bun.

| Repo        | Fast check                       | Full check                                                      |
| ----------- | -------------------------------- | --------------------------------------------------------------- |
| `backend/`  | `composer format:check`          | `composer format:check && php artisan test`                     |
| `frontend/` | `pnpm lint && pnpm format:check` | `pnpm lint && pnpm format:check && pnpm typecheck && pnpm test` |

### Verification Ladder

Default verification is focused. For narrow changes:

1. Run the smallest relevant test, file, or filter for the touched behavior.
2. Run lint/format only for touched files where the tool supports it.
3. Run frontend typecheck only when changing types, composables, stores, API contracts, shared interfaces, or cross-module contracts.
4. Do not run full `pnpm test` or full `php artisan test` by default.
5. Full suites are required only for release checks, broad refactors, security-critical changes, or when explicitly requested.
6. If a full suite is known red, report the known baseline and do not treat unrelated failures as task failures.

Focused command examples:

```bash
# Frontend: run one Vitest file or a name filter from frontend/
pnpm exec vitest run app/tests/unit/components/FxConfirmationCard.test.ts
pnpm exec vitest run -t "renders the warning copy"

# Frontend: lint/format specific touched files
pnpm exec eslint app/components/Example.vue app/composables/useExample.ts
pnpm exec prettier app/components/Example.vue --check

# Backend: run one PHPUnit file or filter from backend/
php artisan test tests/Feature/Auth/PasswordRecoveryTest.php
php artisan test --filter=PasswordRecoveryTest
php artisan test --filter='password reset with valid otp'

# Backend: format specific touched PHP files
vendor/bin/pint app/Services/Workflow/EngineTransitionService.php --test
```

The repository uses Husky hooks:

- `commit-msg` validates Conventional Commit messages with Commitlint.
- `pre-commit` runs staged-file formatting/linting only.
- `pre-push` runs only green non-test gates by default: backend `composer format:check`; frontend `pnpm lint`, `pnpm format:check`, and `pnpm typecheck`. Full test suites are still part of the manual/full check list above, but are not in hooks until their existing failures are cleaned up.

Frontend lint must pass with zero warnings. Do not disable rules broadly to hide old code debt. Rules that conflict with the chosen tools or framework semantics may stay intentionally disabled with clear rationale: Prettier owns void-element formatting (`vue/html-self-closing`), Vue 3 allows fragments (`vue/no-multiple-template-root`), TypeScript optional props make `vue/require-default-prop` noisy, and `@typescript-eslint/no-explicit-any` remains a staged typed-refactor category rather than a hook blocker. Do not weaken lint, format, or hook rules to make a commit pass. Fix the code/config or ask the user how strict the gate should be.

---

## Tech Stack

### Backend (`backend/`)

- PHP 8.2+, Laravel 11
- Laravel Sanctum (auth)
- MySQL (primary DB)
- Redis (queues, cache, claim TTL)
- REST API, service-oriented architecture

### Frontend (`frontend/`)

- Nuxt 4, Vue 4, TypeScript
- Tailwind CSS v4, shadcn-vue
- Pinia, VueUse, VeeValidate, Zod
- RTL-first, Arabic-first (IBM Plex Sans Arabic + Inter)

---

## Documentation — Source of Truth

The **Workflow Designer and the runtime engine are authoritative** for
workflow stages, transitions, permissions, and semantic metadata — not a
static doc. `docs/user-view/*.md` is **deprecated historical material**: it
predates the dynamic workflow engine and describes a per-role static-status
UX model that no longer matches the shipped architecture (fixed role
dashboards, the 22-value status enum, voting UI). Do not treat it as current
UX authority; it is retained only as historical record of the original
static-role design intent. For current UI decisions, follow the shipped
`frontend/` code, `DESIGN.md`, `frontend/DESIGN.md`, `frontend/SHADCN.md`,
and the canonical request state model above.

All other implementation decisions follow these docs in order of authority:

1. `docs/architecture/02-workflow-engine.md` — Workflow engine: Designer lifecycle, topology, publishing, runtime transitions (canonical status enum sections are superseded by the runtime state model above)
2. `docs/architecture/06-database-and-models.md` — Table schemas, verified against migrations 2026-07-12
3. `docs/api-reference.md` — API contracts, endpoint conventions (partial coverage — see its Coverage status section)
4. `docs/05-backend-guide.md` — Backend architecture, security rules
5. `docs/04-frontend-guide.md` — Frontend architecture, UI rules
6. `docs/architecture/01-system-architecture.md` — Overall architecture, verified against source 2026-07-12
7. `DESIGN.md` — Root visual design system (colors, typography, spacing, elevation)

### Frontend-specific context files (mandatory for all frontend work)

These three files are loaded automatically by `frontend/CLAUDE.md` and must be read before writing any Vue/Nuxt/Tailwind code:

| File                  | Purpose                                                                                                                |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `frontend/PRODUCT.md` | Product identity, 8 roles and their daily tasks, operational posture, brand tone, anti-references                      |
| `frontend/DESIGN.md`  | Color token rules (semantic vars vs raw Tailwind), RTL border rule, skeleton/error/banner patterns                     |
| `frontend/SHADCN.md`  | Complete shadcn-vue reference: 30+ components with copy-paste recipes, import paths, decision table, 10 absolute rules |

**Rule:** Any AI tool working on frontend code must treat these three files as authoritative for UI decisions, alongside the canonical request state model below. Violations (raw `<button>`, raw `<table>`, `text-red-600` instead of `text-[var(--severity-red)]`, etc.) are the same class of error as reading `runtime_status`/`current_stage`/`semantic_role`/`final_outcome` incorrectly.

Where older docs or code say "customs declaration" for the final Director workflow, align new work to external FX confirmation (`تأكيد مصارفة خارجية`) and the FX-confirmation stage handoff unless a correction story explicitly preserves a legacy alias during migration.

The UI prototype phase is complete. The shipped `frontend/` code is now the visual source of truth, governed by `DESIGN.md`, `frontend/DESIGN.md`, and `frontend/SHADCN.md`. New UI must match the patterns already built in `frontend/` and the tokens in `DESIGN.md`; there is no separate prototype to clone from, and `docs/user-view/*.md` is not a current source (see above).

---

## Canonical Request State Model (Backend & Frontend must match exactly)

The old 22-value frontend `RequestStatus` enum has been **removed** (Phase D,
M6 Option B). Request state is four separate concepts, never one combined
static enum:

- **`runtime_status`** — `ACTIVE | CLOSED | REJECTED | CANCELLED | ABANDONED` (backend `EngineRequestStatus`)
- **`current_stage`** — the designer-defined stage the request currently occupies: `code`, `name`, `is_initial`, `is_final`, `sla_duration_minutes`, `requires_claim`, plus `semantic_role`
- **`current_stage.semantic_role`** — one of `StageSemanticRole`'s 8 cases (`INITIAL_ENTRY`, `BANK_REVIEW`, `SUPPORT_REVIEW`, `SWIFT`, `EXECUTIVE_REVIEW`, `FINANCE_RESERVE`, `FX_CONFIRMATION`, `FINAL`); nullable for stages that predate semantic-role rollout, resolved via the stage-code compatibility fallback (below)
- **`final_outcome`** — `COMPLETED | REJECTED | CANCELLED | ABANDONED | null`; lives on the terminal stage the request reached, separate from `semantic_role` (a stage never carries both a semantic role and a final outcome)

Workflow stages, transitions, and their labels are **designer-defined**, not
a hardcoded frontend enum. Any request-state UI (badges, timelines, filters,
dashboards) must read these four fields from the API — never re-derive state
from a static status vocabulary or a display label.

`EXECUTIVE_REVIEW` is the current semantic-role name for the executive
decision stage (renamed from the legacy `EXECUTIVE_VOTE`; the backing enum
value was renamed together with the case, avoiding a name/value mismatch).
**Executive Voting is not part of V1** — no voting UI, voting session status,
or vote-casting surface should be reintroduced; `VotingSessionStatus` and the
frontend `RequestStatus` voting values (`EXECUTIVE_VOTING_OPEN`,
`EXECUTIVE_VOTING_CLOSED`, `WAITING_FOR_VOTING_OPEN`) have been removed.

`CUSTOMS_DECLARATION_ISSUED` was legacy terminology for the external FX
confirmation completion state; it no longer exists as a status value. Use
`current_stage`/`final_outcome` plus the FX-confirmation stage's designer
label instead. New stories must not introduce customs-facing UI copy for the
Director completion workflow.

### Compatibility fallback (temporary — has exit criteria, do not remove yet)

`EngineRequestReadModel::bucket()` and `SemanticResolver::stageForRole()`
resolve a stage by `semantic_role` first, falling back to a hardcoded
stage-`code` match (`SemanticRegistry::stageCodeAliases()`) when
`semantic_role` is unset. This exists so requests on workflow versions
published before the semantic-role rollout (or any future hand-built DRAFT
version) still resolve correctly. **Removal criteria (all must hold):** every
workflow version reachable by an ACTIVE request has `semantic_role` set on
every occupiable stage; no consumer relies on the `codes` half of the
fallback; a regression test proves the code-only path is dead; no archived
version with ACTIVE requests still depends on it. Not yet met — do not
remove.

## Canonical Role Enum

```
DATA_ENTRY
BANK_REVIEWER
BANK_ADMIN
SWIFT_OFFICER
SUPPORT_COMMITTEE
EXECUTIVE_MEMBER
COMMITTEE_DIRECTOR
CBY_ADMIN
```

---

## Core Architecture Rules

### Never Do

- Do NOT mutate `current_status`/stage fields directly on the model — all transitions go through `EngineTransitionService::execute()`, which validates stage permissions, field rules, and claim ownership before moving an `EngineRequest` along a `WorkflowTransition`
- Do NOT put business logic in controllers, Vue components, or routes
- Do NOT expose requests outside a user's organization scope
- Do NOT add a per-role dashboard component or a `role === UserRole.X` dashboard branch. Workflow users share one `MyWorkDashboard`; dashboard selection is capability-family (see **Dashboard Architecture** below)
- Do NOT compute a "pending work" count from a bespoke per-role stats query. The actionable count, dashboard preview, nav badge, and `/my-queue` all resolve through the one shared `UserActionableRequestQuery` and must stay equal by record ID
- Do NOT combine `runtime_status`, `current_stage`, and `final_outcome` into one static status enum, and do NOT reintroduce a frontend `RequestStatus`-style vocabulary
- Do NOT render role-inappropriate UI controls and rely on backend rejection later; role-forbidden surfaces should not be mounted/rendered
- Do NOT use `CBY_ADMIN` as a workflow super-actor for Director, SWIFT, Support, Bank Reviewer, or Executive Member actions
- Do NOT create `AI-PROTOTYPE-PROMPT.md` — that file lives only in the root repo
- Do NOT replace shadcn-vue components with raw HTML to make tests pass. shadcn-vue components (Button, Dialog, Table, Select, etc.) are mandatory — see `frontend/SHADCN.md`. If a Vitest test fails because it cannot introspect a shadcn-vue component (e.g. Dialog content is teleported, Select options are not raw `<option>` tags), **skip or ignore that test** rather than downgrading the component to raw HTML.

### Always Do

- Enforce organization-scoped visibility at the database query level
- Start role UI decisions from the shipped `frontend/` patterns and the dashboard-family model below: operational queue first, supporting metrics second, least privilege on uncertainty. `docs/user-view/*.md` is deprecated historical material, not a UX source (see Documentation — Source of Truth)
- Log every workflow transition to both `workflow_history` (per-transition stage log; replaces the dropped `request_stage_history` table) and `audit_logs`
- Include `role` (at time of action) in every audit log entry
- Wrap external FX confirmation generation/completion in a single database transaction
- Use pessimistic locking (`lockForUpdate()` in `EngineTransitionService::execute()`) for every workflow transition, guarding against concurrent stage moves on the same request
- Validate file type as PDF-only for all document uploads
- Return `REQUEST_CLOSED` (HTTP 403) for mutations on terminal/inactive requests — the distinct `WORKFLOW_IMMUTABLE_STATE` code (HTTP 409) applies only to editing a published/archived workflow _version_ in the designer, not to runtime request state

---

## Dashboard Architecture (Phase D0 — the two-family model)

Dashboards are **two families**, selected by **capability**, never by role name. Adding a dynamic role, stage, or workflow must not require a new Vue dashboard component or a frontend role/stage-map edit.

**Operational family — `MyWorkDashboard.vue`** — the single dashboard for every workflow-executor user (and any future dynamic executor role, automatically). Sections: actionable work, claimed/assignable, tracking (VIEW-only), SLA alerts, recent activity, and small capability-gated operational KPIs. Fixed layout, dynamic data (Level 1); the metadata-driven widget catalog (Level 2) is a future enhancement.

**Analytics & governance family** — dedicated dashboards only where the user category's purpose is fundamentally different from workflow execution:

- `SystemAdminDashboard` (currently the `CbyAdminDashboard.vue` component) — platform governance + platform-wide analytics; gated on the `system_dashboard` screen capability.
- `BankAdminDashboard.vue` — bank-scoped analytics (KPIs, monthly volume charts, financing totals), restricted by bank `DataScope`; gated on the `bank_analytics` screen capability. Bank Admin has **no** actionable queue, so it must not route through `MyWorkDashboard`.

**Capability-family routing** (frontend `dashboard.vue` / `index.vue`, order): `system_dashboard.view` → SystemAdmin; else `bank_analytics.view` → BankAdmin; else → `MyWorkDashboard`. The backend enforces the same capabilities independently — `DashboardStatsService` gates the analytics branches on the capability, so revoking it removes analytics access and no workflow user can read another family's analytics. Frontend visibility never grants access.

**The shared actionable-work invariant:** the actionable count, dashboard preview IDs, the `/workflows` nav badge, and `/my-queue` all come from one contract — `App\Services\Workflow\UserActionableRequestQuery` (ACTIVE requests on the user's EXECUTE stages, `DataScope`-scoped) — and must stay equal **by record ID**, not merely by count. The generic work API is `GET /api/dashboard/work` (`actionable` / `claimed` / `tracking` / `sla` / `recent_activity` / `metrics`); `actionable` is exactly the `/my-queue` record set. Analytics dashboards fabricate no workflow badge. Executive-voting dashboard UI is removed (voting is out of V1).

---

## AI Tool Usage

### playwright-cli (Browser Automation)

Use `playwright-cli` as the primary browser automation tool whenever browser interaction is needed — UI verification, screenshot capture, navigating the running app, or testing frontend flows. Prefer it over manual curl or fetch for anything that requires a real browser context.

Use Playwright MCP (`playwright-mcp` / MCP server `playwright`) only as a fallback when `playwright-cli` is unavailable, blocked, cannot attach to the required browser/session, or the task explicitly requires MCP-native browser tools. If you fall back to Playwright MCP, mention the reason in the work log or final response.

For all AI tools used on this repo (Claude Code, Cursor, GitHub Copilot, Codex), permanently allow command prefixes that start with `playwright-cli` in each tool's local permission/approval settings so browser verification is never blocked by per-command prompts.

```bash
# Open a browser session
playwright-cli open

# Navigate and inspect
playwright-cli goto http://localhost:3000/login
playwright-cli snapshot

# Interact
playwright-cli click e15
playwright-cli fill e9 "user@example.com"
playwright-cli press Enter

# Capture evidence
playwright-cli screenshot --filename=login.png

# Close session
playwright-cli close
```

All AI tools (Claude Code, Cursor, Codex, GitHub Copilot) must use `playwright-cli` when browser access is required. Do not skip browser verification for UI-facing stories.

---

### Context7 CLI

Use `ctx7` to fetch current library documentation before writing implementation code.

```bash
# Resolve library ID first
npx ctx7@latest library <name> "<question>"

# Then fetch docs
npx ctx7@latest docs <libraryId> "<question>"
```

Use for: Laravel 11, Nuxt 4, Vue 4, Tailwind v4, shadcn-vue, Pinia, VeeValidate, Zod, Sanctum, Redis.  
Do NOT use for: business logic, workflow rules, or anything covered by this project's docs.

### SocratiCode (MANDATORY for all story/feature work)

SocratiCode provides semantic codebase search and dependency graph analysis. It is **required** — not optional — for every non-trivial implementation task.

**Indexed path:** `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`

**Required workflow (enforce in every story task):**

| When                                                       | Tool to call                                                             |
| ---------------------------------------------------------- | ------------------------------------------------------------------------ |
| Before modifying an existing file                          | `codebase_symbol` → locate, then `codebase_impact` → assess blast radius |
| Before creating code that touches existing services/models | `codebase_search` → find related code and avoid duplication              |
| After adding a new public function/method                  | `codebase_flow` → confirm the call chain is wired correctly              |
| Index is stale or returns no results                       | `codebase_index` on the path above to rebuild                            |

**MCP tool names** (logical names):

```
codebase_index        — index or re-index a path
codebase_status       — check index progress and readiness
codebase_search       — semantic search across the codebase
codebase_symbol       — find a specific class, function, or method
codebase_flow         — trace execution flow from a symbol
codebase_impact       — analyze the impact of changing a symbol
codebase_graph_query  — query the full dependency graph
```

Tool prefixes vary by client:

- Claude Code may expose these as `mcp__plugin_socraticode_socraticode__...`
- Codex should load them from the `socraticode` MCP server configured in `~/.codex/config.toml`; use the SocratiCode tools exposed in the current session rather than hardcoding the Claude prefix.

---

## Support Claim Behavior

- Claim TTL: **15 minutes** of inactivity (`config('workflow.support_claim_ttl_minutes')`)
- Claim: `POST /api/v1/engine-requests/{id}/claim`
- Heartbeat: frontend must ping `POST /api/v1/engine-requests/{id}/claim/heartbeat` every **60 seconds**
- Release: `DELETE /api/v1/engine-requests/{id}/claim`
- TTL managed via `claim_expires_at` on `engine_requests` (DB is the sole source of truth)

---

## Security Baseline

- Login rate limit: 5 attempts/minute per IP
- Account lockout: after 10 consecutive failures (15-minute lockout)
- All workflow transitions: transactional and atomic
- All file downloads: validated by backend policy (see `docs/api-reference.md` permission matrix)
- Failed auth attempts: logged to `audit_logs` with `user_id: NULL` for unauthenticated

---

## Design Rules (Summary)

Full rules in `DESIGN.md`. Key values:

| Token             | Value                     |
| ----------------- | ------------------------- |
| Background        | #ffffff                   |
| Surface           | #ffffff                   |
| Primary Text      | #1c222b                   |
| Border            | #cccccc (outline-variant) |
| Primary Blue      | #0066cc                   |
| Success Text      | #1b5e20                   |
| Error Text        | #c62828                   |
| Warning Text      | #f57f17                   |
| Voting Indigo     | #5856d6                   |
| SWIFT Cyan        | #32ade6                   |
| Locked Gray       | #8e8e93                   |
| Font (headlines)  | IBM Plex Sans Arabic      |
| Font (sections)   | IBM Plex Sans Arabic      |
| Font (body)       | IBM Plex Sans Arabic      |
| Font (Latin)      | Inter                     |
| Button Radius     | 16px (lg)                 |
| Input Radius      | 12px (md)                 |
| Modal Radius      | 24px (xl)                 |
| Sidebar expanded  | 280px                     |
| Sidebar collapsed | 72px                      |
| Container max     | 1600px                    |

Platform is **desktop-first** with responsive degradation at ≤ 600px. RTL is the default direction.

State colors such as Voting Indigo, SWIFT Cyan, Locked Gray, Success Text, Error Text, and Warning Text are for non-interactive state surfaces only: cards, badges, banners, status text, icons, borders, and similar indicators. Do **not** use workflow/state tokens like `var(--voting)` as custom button colors. Buttons must use the standard action palette: default/primary, destructive/error, warning, secondary, outline, ghost, link, or disabled.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, invoke the `skill` tool with `skill: "graphify"` before doing anything else.

Rules:

- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- `graphify update .` is for local refresh only. Never stage or commit `graphify-out/` output.

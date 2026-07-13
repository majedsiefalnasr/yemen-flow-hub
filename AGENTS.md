# Yemen Flow Hub — AI Single File of Truth

This file is the authoritative entry point for all AI tools (Claude Code, Cursor, GitHub Copilot, Codex) working across the Yemen Flow Hub mono-repo. It carries only what an agent must see before opening deeper docs — repository identity, Git rules, quality gates, mandatory frontend context, AI-tool workflows, and a short list of high-risk invariants. Everything else is a pointer.

**Read `docs/README.md` first for the full documentation map.** It is the canonical index; this file does not duplicate it.

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
├── docs/                     ← Project documentation (source of truth) — start at docs/README.md
├── DESIGN.md                 ← Root visual design system (typography, spacing, elevation)
└── AGENTS.md                 ← This file
```

`backend/` and `frontend/` are regular directories in this repository, not submodules or nested Git repositories.

---

## Git Workflow

One Git repository (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`) tracks everything: `docs/`, `backend/`, `frontend/`, and all configs. Commit each change once, from the repository root, regardless of which directory it touches.

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

- Commit message format: `type(scope): description` (Conventional Commits). Type must be a standard type (`feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `build`, `ci`, `perf`, `revert`). Scope is required: `auth`, `backend`, `docs`, `frontend`, `repo`, `settings`, `testing`, `ui`, or `workflow`.
- Examples: `feat(workflow): add support return validation`, `fix(frontend): correct bank queue empty state`.
- Husky `commit-msg` + Commitlint enforce this. Do not bypass with `--no-verify` unless the user explicitly authorizes an emergency exception.
- Co-author all AI-assisted commits: `Co-Authored-By: Claude <noreply@anthropic.com>`
- All commits must remain signed. Never use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false`. If signing fails, stop and fix signing — do not create an unsigned commit.
- Never stage or commit `graphify-out/` — local-only, even when it changes during agent workflows.

---

## Quality Gates

Before editing, run `git -c core.fsmonitor=false status --short` from the repository root. Report existing dirty files before modifying anything, and do not edit dirty files unless directly in scope for the current task.

The project uses `pnpm` for JavaScript tooling — do not migrate to Bun.

| Repo        | Fast check                       | Full check                                                      |
| ----------- | -------------------------------- | --------------------------------------------------------------- |
| `backend/`  | `composer format:check`          | `composer format:check && php artisan test`                     |
| `frontend/` | `pnpm lint && pnpm format:check` | `pnpm lint && pnpm format:check && pnpm typecheck && pnpm test` |

**Verification ladder — default to focused, not full:**

1. Run the smallest relevant test/file/filter for the touched behavior.
2. Run lint/format only on touched files, where the tool supports it.
3. Run frontend typecheck only when changing types, composables, stores, API contracts, shared interfaces, or cross-module contracts.
4. Do not run full `pnpm test` or full `php artisan test` by default — reserve full suites for release checks, broad refactors, security-critical changes, or explicit request.
5. If a full suite is known red, report the known baseline and don't treat unrelated failures as task failures.

```bash
# Frontend: one Vitest file or name filter, from frontend/
pnpm exec vitest run app/tests/unit/components/FxConfirmationCard.test.ts
pnpm exec vitest run -t "renders the warning copy"

# Frontend: lint/format specific touched files
pnpm exec eslint app/components/Example.vue app/composables/useExample.ts
pnpm exec prettier app/components/Example.vue --check

# Backend: one PHPUnit file or filter, from backend/
php artisan test tests/Feature/Auth/PasswordRecoveryTest.php
php artisan test --filter=PasswordRecoveryTest

# Backend: format specific touched PHP files
vendor/bin/pint app/Services/Workflow/EngineTransitionService.php --test
```

Husky hooks: `commit-msg` validates Conventional Commits; `pre-commit` runs staged-file formatting/linting; `pre-push` runs non-test gates only (backend `composer format:check`; frontend `pnpm lint`, `pnpm format:check`, `pnpm typecheck`) — full test suites stay manual until their existing failures are cleaned up.

Frontend lint must pass with zero warnings. Do not disable rules broadly to hide old code debt. A small set of rules stay intentionally disabled with documented rationale (Prettier owns void-element formatting, Vue 3 fragments, optional-prop noise, staged `any` refactor) — see `frontend/CLAUDE.md` for the current list. Never weaken lint, format, or hook rules to make a commit pass; fix the code/config or ask the user how strict the gate should be.

---

## Tech Stack

**Backend (`backend/`):** PHP 8.2+, Laravel 11, Laravel Sanctum, MySQL, Redis (queues/cache), REST API, service-oriented architecture.

**Frontend (`frontend/`):** Nuxt 4, **Vue 3.5**, TypeScript, Tailwind CSS v4, shadcn-vue, Pinia, VueUse, VeeValidate, Zod, RTL-first / Arabic-first (IBM Plex Sans Arabic + Inter).

---

## Documentation Map

Start at **[`docs/README.md`](docs/README.md)** — the canonical index of every live document, with status (live vs. planned). The most relevant entries for agent work:

| Concern                        | Document                                                                                                                           |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| Request state model            | [`docs/architecture/05-request-state-model.md`](docs/architecture/05-request-state-model.md)                                       |
| Workflow engine / behavior     | [`docs/architecture/02-workflow-engine.md`](docs/architecture/02-workflow-engine.md)                                               |
| Permission model               | [`docs/architecture/03-permission-model.md`](docs/architecture/03-permission-model.md)                                             |
| Dashboard architecture         | [`docs/architecture/04-dashboard-architecture.md`](docs/architecture/04-dashboard-architecture.md)                                 |
| System architecture            | [`docs/architecture/01-system-architecture.md`](docs/architecture/01-system-architecture.md)                                       |
| Database and models            | [`docs/architecture/06-database-and-models.md`](docs/architecture/06-database-and-models.md)                                       |
| Backend architecture, security | [`docs/backend-guide.md`](docs/backend-guide.md)                                                                                   |
| Frontend architecture, UI      | [`docs/frontend-guide.md`](docs/frontend-guide.md)                                                                                 |
| API contracts                  | [`docs/api-reference.md`](docs/api-reference.md) (partial coverage — see its Coverage status)                                      |
| Production / deployment / ops  | [`docs/production-guide.md`](docs/production-guide.md)                                                                             |
| Development setup              | [`docs/development-guide.md`](docs/development-guide.md)                                                                           |
| Testing                        | `docs/testing-guide.md` (planned) — current authority is `docs/testing-manual/`                                                    |
| Root visual design system      | [`DESIGN.md`](DESIGN.md)                                                                                                           |
| Historical / audit material    | [`docs/archive/`](docs/archive/README.md) — pre-dynamic-engine and closed-audit records, preserved verbatim, not current authority |

**The Workflow Designer and runtime engine are authoritative** for stages, transitions, permissions, and semantic metadata — never a static doc. `docs/user-view/*.md` is deprecated historical material (predates the dynamic workflow engine, describes fixed-role dashboards, the removed 22-value status enum, and voting UI) and stays in place, unmoved, pending its own separately gated archival step — do not treat it as current UX authority.

### Frontend-specific context (mandatory before writing any Vue/Nuxt/Tailwind code)

`frontend/CLAUDE.md` auto-loads three files that are authoritative for UI decisions:

| File                  | Purpose                                                                                      |
| --------------------- | -------------------------------------------------------------------------------------------- |
| `frontend/PRODUCT.md` | Product identity, 8 roles and daily tasks, operational posture, brand tone                   |
| `frontend/DESIGN.md`  | Color token rules (semantic vars vs. raw Tailwind), RTL border rule, skeleton/error patterns |
| `frontend/SHADCN.md`  | Full shadcn-vue reference: components, recipes, import paths, decision table, absolute rules |

Violations (raw `<button>`, raw `<table>`, `text-red-600` instead of a semantic token, etc.) are the same class of error as misreading the request state model.

Where older docs or code say "customs declaration" for the final Director workflow, align new work to external FX confirmation (`تأكيد مصارفة خارجية`) unless a correction story explicitly preserves a legacy alias during migration. The UI prototype phase is complete — shipped `frontend/` code, `DESIGN.md`, `frontend/DESIGN.md`, and `frontend/SHADCN.md` are the visual source of truth; there is no separate prototype to clone from.

---

## High-Risk Invariants

These are the facts agents get wrong most often. Full detail is in the linked docs above — this is the short version so nothing below has to be rediscovered by trial and error.

- **Request state is four separate fields**, never one combined enum: `runtime_status`, `current_stage`, `current_stage.semantic_role`, `final_outcome`. The old 22-value frontend `RequestStatus` enum is removed. Details: [`docs/architecture/05-request-state-model.md`](docs/architecture/05-request-state-model.md).
- **`semantic_role` is nullable** (predates rollout on some stages) and resolved via a documented, temporary stage-code compatibility fallback — see the request state model doc for exit criteria. **The `semantic_role`/`final_outcome` split is architectural convention, not a code- or database-enforced guard** — no cross-field validation currently prevents a stage from carrying both. Do not describe it as an enforced invariant.
- **Executive Voting is out of V1.** No voting UI, voting session status, or vote-casting surface should exist or be reintroduced.
- **Never mutate `current_status`/stage fields directly on the model.** All transitions go through `EngineTransitionService::execute()` (stage permissions, field rules, claim ownership, pessimistic locking via `lockForUpdate()`), logged to both `workflow_history` and `audit_logs` with `role` at time of action.
- **Never expose requests outside a user's organization scope** — enforce at the database query level.
- **Dashboards are two families, selected by capability, never by role name**: the operational `MyWorkDashboard.vue` for every workflow-executor role, and dedicated analytics dashboards (`SystemAdminDashboard`, `BankAdminDashboard.vue`) for governance roles. **Dashboard component selection is capability-led, but route admission and some backend analytics dispatch still contain fixed-role constraints** — it is not capability-only end to end. Do not add a per-role dashboard component or compute a bespoke per-role "pending work" count; the actionable count, dashboard preview, nav badge, and `/my-queue` all resolve through `UserActionableRequestQuery` and must stay equal by record ID. Details: [`docs/architecture/04-dashboard-architecture.md`](docs/architecture/04-dashboard-architecture.md).
- **Never use `CBY_ADMIN` as a workflow super-actor** for Director, SWIFT, Support, Bank Reviewer, or Executive Member actions.
- **Never render role-inappropriate UI controls and rely on backend rejection later** — role-forbidden surfaces should not be mounted.
- **Never replace shadcn-vue components with raw HTML to make tests pass.** If a Vitest test can't introspect a shadcn-vue component (teleported Dialog content, non-native Select options), skip or ignore that test instead.
- **Runtime support-claim TTL comes from the admin `support_claim_ttl` setting via `SettingResolver`**, baked into `claim_expires_at` at claim/heartbeat time — not from any Laravel config key. `workflow.support_claim_ttl_minutes` is a legacy key read only by a database seeder, never by runtime code. Same pattern applies to login lockout (below).
- **Do NOT create `AI-PROTOTYPE-PROMPT.md`** — that file lives only in the root repo.

### Canonical Role Enum

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

## Security Baseline

- Login rate limit: 5 attempts/minute per IP.
- **Account lockout is admin-configurable** via `SettingResolver` (same mechanism as claim TTL) — config default is 5 consecutive failures / 15-minute lockout, not a fixed value.
- All workflow transitions: transactional and atomic.
- All file downloads: validated by backend policy — see [`docs/api-reference.md`](docs/api-reference.md) permission matrix.
- Failed auth attempts: logged to `audit_logs` with `user_id: NULL` for unauthenticated.
- All document uploads: PDF-only.
- Mutations on terminal/inactive requests return `REQUEST_CLOSED` (HTTP 403); `WORKFLOW_IMMUTABLE_STATE` (HTTP 409) is distinct — it applies only to editing a published/archived workflow _version_ in the Designer, not to runtime request state.

Do not weaken security, organization-scope, transition-service, audit, or frontend component rules above without explicit user authorization.

---

## AI Tool Usage

### playwright-cli (Browser Automation)

Use `playwright-cli` as the primary tool for any browser interaction — UI verification, screenshots, navigating the running app, testing frontend flows. Fall back to Playwright MCP only when `playwright-cli` is unavailable, blocked, or the task explicitly requires MCP-native tools (mention the reason if you fall back). Permanently allow `playwright-cli`-prefixed commands in each AI tool's local permission settings so verification is never blocked by per-command prompts. Do not skip browser verification for UI-facing stories.

```bash
playwright-cli open
playwright-cli goto http://localhost:3000/login
playwright-cli snapshot
playwright-cli click e15
playwright-cli fill e9 "user@example.com"
playwright-cli press Enter
playwright-cli screenshot --filename=login.png
playwright-cli close
```

### Context7 CLI

Fetch current library documentation before writing implementation code:

```bash
npx ctx7@latest library "<name>" "<question>"
npx ctx7@latest docs <libraryId> "<question>"
```

Use for: Laravel 11, Nuxt 4, Vue 3.5, Tailwind v4, shadcn-vue, Pinia, VeeValidate, Zod, Sanctum, Redis. Do NOT use for business logic, workflow rules, or anything covered by this project's docs.

### SocratiCode (mandatory for all story/feature work)

Semantic codebase search and dependency graph analysis — required, not optional, for every non-trivial implementation task.

**Indexed path:** `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`

| When                                                       | Tool to call                                                             |
| ---------------------------------------------------------- | ------------------------------------------------------------------------ |
| Before modifying an existing file                          | `codebase_symbol` → locate, then `codebase_impact` → assess blast radius |
| Before creating code that touches existing services/models | `codebase_search` → find related code and avoid duplication              |
| After adding a new public function/method                  | `codebase_flow` → confirm the call chain is wired correctly              |
| Index is stale or returns no results                       | `codebase_index` on the path above to rebuild                            |

Logical tool names: `codebase_index`, `codebase_status`, `codebase_search`, `codebase_symbol`, `codebase_flow`, `codebase_impact`, `codebase_graph_query`. Claude Code exposes these as `mcp__plugin_socraticode_socraticode__...`; Codex loads them from the `socraticode` MCP server in `~/.codex/config.toml` — use whatever tools are exposed in the current session rather than hardcoding a prefix.

### graphify

Knowledge graph at `graphify-out/` (god nodes, community structure, cross-file relationships).

- For codebase questions, run `graphify query "<question>"` first when `graphify-out/graph.json` exists — a scoped subgraph, smaller than `GRAPH_REPORT.md` or raw grep. Use `graphify path "<A>" "<B>"` for relationships, `graphify explain "<concept>"` for focused concepts.
- Dirty `graphify-out/` files after hooks/incremental updates are expected — not a reason to skip graphify.
- Use `graphify-out/wiki/index.md` for broad navigation when present; read `GRAPH_REPORT.md` only for broad architecture review or when query/path/explain don't surface enough.
- After modifying code, run `graphify update .` (local refresh only, AST-only, no API cost). Never stage or commit `graphify-out/`.

---

## Design Rules (Summary)

Full rules in [`DESIGN.md`](DESIGN.md). Platform is desktop-first with responsive degradation at ≤ 600px; RTL is the default direction. State colors (Voting Indigo, SWIFT Cyan, Locked Gray, Success/Error/Warning Text) are for non-interactive state surfaces only — never as custom button colors. Buttons use the standard action palette: default/primary, destructive/error, warning, secondary, outline, ghost, link, or disabled.

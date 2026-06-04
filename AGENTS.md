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
yemen-flow-hub/               ← Root repo (git@github.com:majedsiefalnasr/yemen-flow-hub.git)
├── backend/                  ← Laravel 11 API (git@github.com:ultimate-eg/yemen-flow-hub-backend.git)
├── frontend/                 ← Nuxt 4 app (git@github.com:ultimate-eg/yemen-flow-hub-frontend.git)
│   ├── PRODUCT.md            ← Product identity, users, roles, operational posture, brand tone
│   ├── DESIGN.md             ← Frontend design token rules, RTL patterns, color token usage
│   ├── SHADCN.md             ← shadcn-vue component reference: recipes, imports, decision table
│   └── CLAUDE.md             ← Frontend AI instructions (loads PRODUCT.md + DESIGN.md + SHADCN.md)
├── docs/                     ← Project documentation (source of truth)
├── DESIGN.md                 ← Root visual design system (typography, spacing, elevation)
├── AI-ENGINEERING-PROMPT.md  ← Full engineering context
└── AGENTS.md                 ← This file
```

---

## Git Workflow — Monorepo + Two Team Repos

The project uses **three git repositories** with overlapping coverage:

| Repo | Remote | Tracks |
| ---- | ------ | ------ |
| Root (monorepo) | `git@github.com:majedsiefalnasr/yemen-flow-hub.git` | Everything: `docs/`, `backend/`, `frontend/`, configs |
| Backend team repo | `git@github.com:ultimate-eg/yemen-flow-hub-backend.git` | `backend/` only — for the backend team |
| Frontend team repo | `git@github.com:ultimate-eg/yemen-flow-hub-frontend.git` | `frontend/` only — for the frontend team |

**Why three repos?**
- The root monorepo is the source of truth — all code lives here.
- Backend and frontend each have their own repo so team members only see their part of the codebase.
- Both team repos stay in sync with the corresponding subdirectory in the root monorepo.

### Commit Rules

Each change must be committed to **all applicable repos**:

| Change location | Commit to |
| --------------- | --------- |
| `docs/`, `AGENTS.md`, `DESIGN.md`, root configs | Root repo only (run `git` from `/`) |
| `backend/` code | Root repo (`git` from `/`) **AND** backend team repo (`git` from `backend/`) |
| `frontend/` code | Root repo (`git` from `/`) **AND** frontend team repo (`git` from `frontend/`) |

**Commit workflow for backend changes:**
```bash
# 1. Commit to backend team repo
cd backend
git add <files>
git commit -m "feat(workflow): ..."

# 2. Commit same change to root monorepo
cd ..
git add backend/<files>
git commit -m "feat(workflow): ..."
```

**Commit workflow for frontend changes:**
```bash
# 1. Commit to frontend team repo
cd frontend
git add <files>
git commit -m "feat(voting): ..."

# 2. Commit same change to root monorepo
cd ..
git add frontend/<files>
git commit -m "feat(voting): ..."
```

- Commit message format: `type(scope): description` (conventional commits)
- Commit message scope is required. Allowed scopes are `auth`, `backend`, `docs`, `frontend`, `repo`, `settings`, `testing`, `ui`, and `workflow`.
- Commit message type must be a Conventional Commit type such as `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `build`, `ci`, `perf`, or `revert`.
- Examples: `feat(workflow): add support return validation`, `fix(frontend): correct bank queue empty state`, `chore(repo): add lint and format tooling`.
- Frontend and backend team repos enforce commit messages with Husky `commit-msg` hooks and Commitlint. Do not bypass hooks with `--no-verify` unless the user explicitly authorizes an emergency exception.
- Co-author all AI-assisted commits with: `Co-Authored-By: Claude <noreply@anthropic.com>`
- Keep commit messages identical between the team repo and the root monorepo for the same change
- All commits must remain signed. Do NOT use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false` as a workaround.
- If signing fails, stop and fix the Git signing setup instead of creating an unsigned commit.
- Never add or commit generated artifacts from `graphify-out/`, `_bmad-output/implementation-artifacts/`, or `_bmad-output/test-artifacts/` in any repo. Keep them local only, even when they change during agent workflows.

### Quality Gates

Use the repository quality scripts before committing:

| Repo | Fast check | Full check |
| ---- | ---------- | ---------- |
| `backend/` | `composer format:check` | `composer format:check && php artisan test` |
| `frontend/` | `pnpm lint && pnpm format:check` | `pnpm lint && pnpm format:check && pnpm typecheck && pnpm test` |

Frontend and backend team repos use Husky hooks:
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

All implementation decisions must follow these docs in order of authority:

1. `docs/user-view/*.md` — enterprise-grade per-role UX specifications (operational posture, dashboard structure, page interaction patterns, status presentation, density, micro-copy, RTL, non-visibility)
2. `docs/01-workflow-and-business-rules.md` — Workflow stages, business rules, status enums
3. `docs/03-database-and-models.md` — Canonical status/role enums, table schemas
4. `docs/06-api-reference.md` — API contracts, endpoint conventions
5. `docs/05-backend-guide.md` — Backend architecture, security rules
6. `docs/04-frontend-guide.md` — Frontend architecture, UI rules
7. `docs/02-system-architecture.md` — Overall architecture
8. `DESIGN.md` — Root visual design system (colors, typography, spacing, elevation)
9. `AI-ENGINEERING-PROMPT.md` — Full engineering context and anti-patterns

### Frontend-specific context files (mandatory for all frontend work)

These three files are loaded automatically by `frontend/CLAUDE.md` and must be read before writing any Vue/Nuxt/Tailwind code:

| File | Purpose |
| ---- | ------- |
| `frontend/PRODUCT.md` | Product identity, 8 roles and their daily tasks, operational posture, brand tone, anti-references |
| `frontend/DESIGN.md` | Color token rules (semantic vars vs raw Tailwind), RTL border rule, skeleton/error/banner patterns |
| `frontend/SHADCN.md` | Complete shadcn-vue reference: 30+ components with copy-paste recipes, import paths, decision table, 10 absolute rules |

**Rule:** Any AI tool working on frontend code must treat these three files as equally authoritative as `docs/user-view/*.md` for UI decisions. Violations (raw `<button>`, raw `<table>`, `text-red-600` instead of `text-[var(--severity-red)]`, etc.) are the same class of error as using a wrong status enum.

`docs/user-view/*.md` intentionally supersedes older customs-declaration terminology. Where older docs or code say "customs declaration" for the final Director workflow, align new work to external FX confirmation (`تأكيد مصارفة خارجية`) and the `FX_CONFIRMATION_PENDING` handoff unless a correction story explicitly preserves a legacy alias during migration.

The UI prototype phase is complete. The shipped `frontend/` code is now the visual source of truth, governed by `DESIGN.md`, `frontend/DESIGN.md`, `frontend/SHADCN.md`, and `docs/user-view/*.md`. New UI must match the patterns already built in `frontend/` and the tokens in `DESIGN.md`; there is no separate prototype to clone from.

---

## Canonical Status Enum (Backend & Frontend must match exactly)

```
DRAFT
DRAFT_REJECTED_INTERNAL
SUBMITTED
BANK_REVIEW
BANK_RETURNED
BANK_REJECTED
BANK_APPROVED
SUPPORT_REVIEW_PENDING
SUPPORT_REVIEW_IN_PROGRESS
SUPPORT_APPROVED
SUPPORT_REJECTED
SUPPORT_RETURNED
WAITING_FOR_SWIFT
SWIFT_UPLOADED
WAITING_FOR_VOTING_OPEN
EXECUTIVE_VOTING_OPEN
EXECUTIVE_VOTING_CLOSED
EXECUTIVE_APPROVED
EXECUTIVE_REJECTED
FX_CONFIRMATION_PENDING
CUSTOMS_DECLARATION_ISSUED
COMPLETED
```

`BANK_RETURNED` (Story 8.1) is the editable state when a Bank Reviewer returns a submitted request to Data Entry for correction. `SUPPORT_RETURNED` (Story 8.2) is the editable state when the Support Committee returns an approved bank request to the originating bank for correction. `BANK_REJECTED` (Story 8.3) is the terminal state when a Bank Reviewer rejects a request outright (no re-submission path).

`CUSTOMS_DECLARATION_ISSUED` is legacy terminology retained only until the external FX confirmation migration is completed. New stories must not introduce additional customs-facing UI copy for the Director completion workflow.

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
- Do NOT mutate `current_status` directly on the model — all transitions via `WorkflowService::transition()`
- Do NOT put business logic in controllers, Vue components, or routes
- Do NOT expose requests outside a user's organization scope
- Do NOT generate shared admin dashboards — every view is queue-scoped and role-scoped
- Do NOT use statuses not in the canonical enum above
- Do NOT render role-inappropriate UI controls and rely on backend rejection later; role-forbidden surfaces should not be mounted/rendered
- Do NOT use `CBY_ADMIN` as a workflow super-actor for Director, SWIFT, Support, Bank Reviewer, or Executive Member actions
- Do NOT create `AI-PROTOTYPE-PROMPT.md` — that file lives only in the root repo
- Do NOT replace shadcn-vue components with raw HTML to make tests pass. shadcn-vue components (Button, Dialog, Table, Select, etc.) are mandatory — see `frontend/SHADCN.md`. If a Vitest test fails because it cannot introspect a shadcn-vue component (e.g. Dialog content is teleported, Select options are not raw `<option>` tags), **skip or ignore that test** rather than downgrading the component to raw HTML.

### Always Do
- Enforce organization-scoped visibility at the database query level
- Start role UI decisions from the relevant `docs/user-view/{role}.md`: operational queue first, supporting metrics second, least privilege on uncertainty
- Log every workflow transition to both `request_stage_history` and `audit_logs`
- Include `role` (at time of action) in every audit log entry
- Wrap external FX confirmation generation/completion in a single database transaction
- Use pessimistic locking for vote submission and voting session closure
- Validate file type as PDF-only for all document uploads
- Return `WORKFLOW_IMMUTABLE_STATE` (HTTP 403) for mutations on terminal states

---

## AI Tool Usage

### playwright-cli (Browser Automation)
Use `playwright-cli` whenever browser interaction is needed — UI verification, screenshot capture, navigating the running app, or testing frontend flows. Prefer it over manual curl or fetch for anything that requires a real browser context.

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

SocratiCode provides semantic codebase search and dependency graph analysis. It is **required** — not optional — for every BMAD dev-story run and any non-trivial implementation task.

**Indexed path:** `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`

**Required workflow (enforce in every story task):**

| When | Tool to call |
| ---- | ------------ |
| Before modifying an existing file | `codebase_symbol` → locate, then `codebase_impact` → assess blast radius |
| Before creating code that touches existing services/models | `codebase_search` → find related code and avoid duplication |
| After adding a new public function/method | `codebase_flow` → confirm the call chain is wired correctly |
| Index is stale or returns no results | `codebase_index` on the path above to rebuild |

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

**BMAD integration:** The `_bmad/custom/bmad-dev-story.toml` file enforces SocratiCode checks as persistent facts on every story activation. On startup, the dev-story workflow calls `codebase_search` to verify the index is live and triggers `codebase_index` automatically if it is not.

---

## Support Claim Behavior

- Claim TTL: **15 minutes** of inactivity
- Heartbeat: frontend must ping `POST /api/workflow/{id}/claim-support-review/heartbeat` every **60 seconds**
- Release: `DELETE /api/workflow/{id}/claim-support-review`
- TTL managed via Redis key: `support_claim:{request_id}`

---

## Security Baseline

- Login rate limit: 5 attempts/minute per IP
- Account lockout: after 10 consecutive failures (15-minute lockout)
- All workflow transitions: transactional and atomic
- All file downloads: validated by backend policy (see `docs/06-api-reference.md` permission matrix)
- Failed auth attempts: logged to `audit_logs` with `user_id: NULL` for unauthenticated

---

## Design Rules (Summary)

Full rules in `DESIGN.md`. Key values:

| Token           | Value                   |
| --------------- | ----------------------- |
| Background      | #ffffff                 |
| Surface         | #ffffff                 |
| Primary Text    | #1c222b                 |
| Border          | #cccccc (outline-variant) |
| Primary Blue    | #0066cc                 |
| Success Text    | #1b5e20                 |
| Error Text      | #c62828                 |
| Warning Text    | #f57f17                 |
| Voting Indigo   | #5856d6                 |
| SWIFT Cyan      | #32ade6                 |
| Locked Gray     | #8e8e93                 |
| Font (headlines)| Cairo                   |
| Font (sections) | Tajawal                 |
| Font (body)     | IBM Plex Sans Arabic    |
| Font (Latin)    | Inter                   |
| Button Radius   | 16px (lg)               |
| Input Radius    | 12px (md)               |
| Modal Radius    | 24px (xl)               |
| Sidebar expanded | 280px                  |
| Sidebar collapsed | 72px                  |
| Container max   | 1600px                  |

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

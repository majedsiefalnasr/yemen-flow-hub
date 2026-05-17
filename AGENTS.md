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
├── docs/                     ← Project documentation (source of truth)
├── lovable/                  ← Lovable prototype (reference only, do NOT modify)
├── DESIGN.md                 ← Visual design system
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
- Co-author all AI-assisted commits with: `Co-Authored-By: Claude <noreply@anthropic.com>`
- Keep commit messages identical between the team repo and the root monorepo for the same change

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

1. `docs/01-workflow-and-business-rules.md` — Workflow stages, business rules, status enums
2. `docs/03-database-and-models.md` — Canonical status/role enums, table schemas
3. `docs/06-api-reference.md` — API contracts, endpoint conventions
4. `docs/05-backend-guide.md` — Backend architecture, security rules
5. `docs/04-frontend-guide.md` — Frontend architecture, UI rules
6. `docs/02-system-architecture.md` — Overall architecture
7. `DESIGN.md` — Visual design system (colors, typography, layout)
8. `AI-ENGINEERING-PROMPT.md` — Full engineering context and anti-patterns

**lovable/** is a reference prototype only. Do not copy or adapt its code without explicit instruction.

---

## Canonical Status Enum (Backend & Frontend must match exactly)

```
DRAFT
DRAFT_REJECTED_INTERNAL
SUBMITTED
BANK_REVIEW
BANK_APPROVED
SUPPORT_REVIEW_PENDING
SUPPORT_REVIEW_IN_PROGRESS
SUPPORT_APPROVED
SUPPORT_REJECTED
WAITING_FOR_SWIFT
SWIFT_UPLOADED
WAITING_FOR_VOTING_OPEN
EXECUTIVE_VOTING_OPEN
EXECUTIVE_VOTING_CLOSED
EXECUTIVE_APPROVED
EXECUTIVE_REJECTED
CUSTOMS_DECLARATION_ISSUED
COMPLETED
```

## Canonical Role Enum

```
DATA_ENTRY
BANK_REVIEWER
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
- Do NOT create `AI-PROTOTYPE-PROMPT.md` — that file lives only in the root repo
- Do NOT modify anything inside `lovable/`

### Always Do
- Enforce organization-scoped visibility at the database query level
- Log every workflow transition to both `request_stage_history` and `audit_logs`
- Include `role` (at time of action) in every audit log entry
- Wrap customs declaration generation in a single database transaction
- Use pessimistic locking for vote submission and voting session closure
- Validate file type as PDF-only for all document uploads
- Return `WORKFLOW_IMMUTABLE_STATE` (HTTP 403) for mutations on terminal states

---

## AI Tool Usage

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

| Token           | Value     |
| --------------- | --------- |
| Background      | #f5f5f7   |
| Surface         | #ffffff   |
| Primary Text    | #1d1d1f   |
| Border          | #d2d2d7   |
| Primary Blue    | #0071e3   |
| Approved Green  | #34c759   |
| Rejected Red    | #ff3b30   |
| Pending Amber   | #ff9f0a   |
| Voting Indigo   | #5856d6   |
| SWIFT Cyan      | #32ade6   |
| Locked Gray     | #8e8e93   |
| Font (Arabic)   | IBM Plex Sans Arabic |
| Font (English)  | Inter     |
| Card Radius     | 12px      |

Platform is **desktop-first** with responsive degradation at ≤ 600px. RTL is the default direction.

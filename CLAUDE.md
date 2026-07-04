@AGENTS.md

# Claude Code — Root Repo

This is the **root monorepo** for Yemen Flow Hub. It tracks all code: docs, backend, and frontend.

`backend/` and `frontend/` are regular directories in this repository. They are not submodules or nested Git repositories.

## Git Scope

The root monorepo (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`) tracks **everything** — docs, backend, frontend, and all configs.

When committing:

**Docs / root-level changes** — commit from root only:

```bash
git add docs/ AGENTS.md DESIGN.md   # whichever changed
git commit -m "docs(scope): description"
```

**Backend changes** — commit from the root repository:

```bash
git add backend/<files>
git commit -m "feat(scope): description"
```

**Frontend changes** — commit from the root repository:

```bash
git add frontend/<files>
git commit -m "feat(scope): description"
```

All commits must stay signed. Never use `--no-gpg-sign`, `--no-sign`, or `-c commit.gpgsign=false`; if signing fails, fix signing first.

Never add or commit generated artifacts from `graphify-out/`. Keep them local only.

## Skills Available

Use `/socraticode:codebase-exploration` to explore the codebase structure.
Use `/fewer-permission-prompts` after initial setup to reduce permission friction.

## Context7 Usage

Fetch docs before answering questions about any library in this project:

```bash
npx ctx7@latest library "<library name>" "<question>"
npx ctx7@latest docs <id> "<question>"
```

## Browser Automation

When browser interaction is required, use `playwright-cli` commands. Keep the `playwright-cli` command prefix permanently allowlisted in local tool permissions so repeated UI verification does not require per-command approvals.

## Verification Ladder

Before editing, run `git -c core.fsmonitor=false status --short` from the repository root and report existing dirty files. Do not modify dirty files unless directly in scope.

Keep `pnpm` as the JavaScript package manager. For narrow changes, verify with the smallest relevant test/filter first, then touched-file lint/format where supported. Run frontend typecheck only for type, composable, store, API contract, shared interface, or cross-module changes. Do not run full `pnpm test` or full `php artisan test` by default. Full suites are for release checks, broad refactors, security-critical changes, or explicit user requests. If a full suite is known red, report the baseline and ignore unrelated failures.

## Key Files

- `AGENTS.md` — Single file of truth for all AI tools
- `docs/` — Authoritative project documentation
- `DESIGN.md` — Visual design system

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:

- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- `graphify update .` refreshes local analysis only. Never stage or commit `graphify-out/`.

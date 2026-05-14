@AGENTS.md

# Claude Code — Root Repo

This is the **root monorepo** for Yemen Flow Hub. It tracks all code: docs, backend, and frontend.

`backend/` and `frontend/` also have their own independent git repos so each team only sees their part.

## Git Scope

The root monorepo (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`) tracks **everything** — docs, backend, frontend, and all configs.

When committing:

**Docs / root-level changes** — commit from root only:
```bash
git add docs/ AGENTS.md DESIGN.md   # whichever changed
git commit -m "docs(scope): description"
```

**Backend changes** — commit to both repos:
```bash
# 1. Backend team repo
cd backend && git add <files> && git commit -m "feat(scope): description"
# 2. Root monorepo
cd .. && git add backend/<files> && git commit -m "feat(scope): description"
```

**Frontend changes** — commit to both repos:
```bash
# 1. Frontend team repo
cd frontend && git add <files> && git commit -m "feat(scope): description"
# 2. Root monorepo
cd .. && git add frontend/<files> && git commit -m "feat(scope): description"
```

## Skills Available

Use `/bmad-*` skills for project management, PRD, architecture, and sprint planning.
Use `/socraticode:codebase-exploration` to explore the codebase structure.
Use `/fewer-permission-prompts` after initial setup to reduce permission friction.

## Context7 Usage

Fetch docs before answering questions about any library in this project:
```bash
npx ctx7@latest library "<library name>" "<question>"
npx ctx7@latest docs <id> "<question>"
```

## Key Files

- `AGENTS.md` — Single file of truth for all AI tools
- `docs/` — Authoritative project documentation
- `DESIGN.md` — Visual design system
- `AI-ENGINEERING-PROMPT.md` — Full engineering context
- `lovable/` — Reference prototype, do NOT modify

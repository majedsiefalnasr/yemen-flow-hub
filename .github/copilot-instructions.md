# Yemen Flow Hub — GitHub Copilot Instructions (Root Repo)

This is the root repository for Yemen Flow Hub, an internal government banking regulatory workflow platform for the Central Bank of Yemen. It contains documentation, design system, and AI configuration files only.

## Source of Truth
Read `AGENTS.md` at the root. All workflow rules, enums, and architecture decisions are defined there and in `docs/`.

## Git Scope
Root monorepo (`git@github.com:majedsiefalnasr/yemen-flow-hub.git`) tracks **everything**: docs, backend, frontend.

Backend and frontend also each have their own team repos. Every change to `backend/` or `frontend/` must be committed to both the team repo (from inside that directory) and the root monorepo (from the root).

- Docs/root changes → root monorepo only
- Backend changes → `backend/` team repo + root monorepo
- Frontend changes → `frontend/` team repo + root monorepo
- Commit format: `type(scope): description`

## Key Rules
- `lovable/` is read-only reference code — never suggest modifying it
- Docs in `docs/` override all Copilot suggestions about workflow or business logic
- Use Context7 CLI for library documentation: `npx ctx7@latest library "<name>" "<question>"`
- Use SocratiCode MCP for codebase exploration before suggesting changes

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
- For browser automation and UI verification, use `playwright-cli`; keep command prefixes that start with `playwright-cli` allowlisted in local approvals.
- Never add or commit generated artifacts from `graphify-out/`, `_bmad-output/implementation-artifacts/`, or `_bmad-output/test-artifacts/`. Keep them local only.

## graphify

For any question about this repo's architecture, structure, components, or how to add/modify/find
code, your first action should be `graphify query "<question>"` when `graphify-out/graph.json`
exists. Use `graphify path "<A>" "<B>"` for relationship questions and `graphify explain "<concept>"`
for focused-concept questions. These return a scoped subgraph, usually much smaller than the full
report or raw grep output.

Triggers: "how do I…", "where is…", "what does … do", "add/modify a <component>",
"explain the architecture", or anything that depends on how files or classes relate.

If `graphify-out/wiki/index.md` exists, use it for broad navigation. Read `graphify-out/GRAPH_REPORT.md`
only for broad architecture review or when query/path/explain do not surface enough context. Only read
source files when (a) modifying/debugging specific code, (b) the graph lacks the needed detail, or
(c) the graph is missing or stale.

Type `/graphify` in Copilot Chat to build or update the graph.
`/graphify` updates local analysis only. Never stage or commit `graphify-out/`.

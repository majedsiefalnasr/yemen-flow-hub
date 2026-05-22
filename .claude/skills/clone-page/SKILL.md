---
name: clone-page
description: 'Clone a Lovable React page to a Nuxt Vue page at 1:1 visual parity following the canonical 8-step workflow, producing the parity-evidence triplet required by the BMad dev-story gate. Use when the user says "clone page [path-or-name]" or "/clone-page [path-or-name]".'
---

# Clone-Page Skill (Yemen Flow Hub — Epic 9)

**Goal:** Port one Lovable React page to its Nuxt Vue counterpart at 1:1 visual parity and produce the committed parity-evidence triplet (`lovable.png`, `current.png`, `side-by-side.png`) required by the BMad dev-story workflow gate (`_bmad/custom/bmad-dev-story.toml`).

**Authoritative procedure:** `docs/ui-parity/clone-page-workflow.md`. This skill is a thin executable wrapper around that procedure — when in doubt, read the doc.

---

## Argument

`{page}` — required. One of:

- A Lovable source path (e.g. `lovable/src/pages/Login.tsx`).
- A Nuxt target path (e.g. `frontend/app/pages/login.vue`).
- A page name in `<area>/<page>` form (e.g. `auth/login`, `requests/detail`).

If the argument is omitted or ambiguous, ASK the user to disambiguate before proceeding.

---

## Procedure

Execute the 8 steps in `docs/ui-parity/clone-page-workflow.md` in order. Do not skip steps. Do not invent a shortcut.

1. **Identify the target.** Resolve `{page}` to (a) Lovable source path, (b) Nuxt target path, (c) parity-evidence `<area>/<page>` directory. Use the derivation table in `frontend/scripts/check-parity-evidence.ts` if needed.
2. **Launch both apps at matched viewport** via dev-browser (1440×900 desktop and 390×844 mobile, RTL default).
3. **Screenshot the Lovable page** → `_bmad-output/parity-evidence/<area>/<page>/lovable.png`.
4. **Screenshot the current Nuxt page** (pre-port baseline) → `_bmad-output/parity-evidence/<area>/<page>/current.png`.
5. **Port markup, composables, and stores.** Translate React → Vue, mirror for RTL using the RTL Mirror Checklist in the doc, preserve Arabic copy verbatim, use shadcn-vue primitives, wire to real Laravel APIs.
6. **Re-screenshot the Nuxt page** post-port and overwrite `current.png`.
7. **Produce `side-by-side.png`** (lovable left, current right, identical scale).
8. **Stage and commit** the three PNGs and the Vue/CSS source change together. Wait for user sign-off before claiming completion.

---

## Verification (mandatory before completion)

```bash
npx tsx frontend/scripts/check-parity-evidence.ts frontend/app/<...>.vue
```

Must exit 0. If it exits non-zero, return to the missing step (typically step 7 — the side-by-side composite). Do NOT use `// @parity-exempt` to silence a real visual change.

---

## Output artifacts

- `_bmad-output/parity-evidence/<area>/<page>/lovable.png`
- `_bmad-output/parity-evidence/<area>/<page>/current.png`
- `_bmad-output/parity-evidence/<area>/<page>/side-by-side.png`
- The ported Vue component / page under `frontend/app/...`
- Any composable, store, or service wiring required to back the page with real APIs

---

## Constraints

- `lovable/` is read-only — never modify it.
- All commits stay signed (no `--no-gpg-sign`).
- Frontend changes commit to both the frontend team repo and the root monorepo per AGENTS.md.
- No mock data; wire to real Laravel APIs.
- shadcn-vue primitives only — no direct React/TanStack copy.

---

## References

- Workflow doc: `docs/ui-parity/clone-page-workflow.md`
- BMad gate: `_bmad/custom/bmad-dev-story.toml`
- Check script: `frontend/scripts/check-parity-evidence.ts`
- Doctrine: `AGENTS.md`, root `CLAUDE.md`, `docs/04-frontend-guide.md` (Visual Parity Workflow), `DESIGN.md` (Source of truth)

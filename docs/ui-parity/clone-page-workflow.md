# Clone-Page Workflow — Per-Page UI Parity Procedure

This document defines the canonical 8-step procedure for porting a Lovable React page to a Nuxt Vue page at 1:1 visual parity. Every UI-touching story must follow it. The BMad dev-story workflow gate (`_bmad/custom/bmad-dev-story.toml`) refuses to mark any UI story complete without the parity-evidence triplet this procedure produces.

> **Source of truth:** `lovable/screenshots/` is the final visual authority. When `DESIGN.md` tokens conflict with rendered Lovable screenshots, the screenshot wins (see `DESIGN.md` Source-of-truth note).

---

## When to Use This Workflow

Use it whenever your story's File List touches:

- `frontend/app/**/*.vue`
- `frontend/app/assets/css/**`

Skip it (with the `// @parity-exempt` first-line marker) only when your edit is comment-only, type-only, or otherwise non-rendering.

---

## The 8-Step Procedure

### 1. Identify the target

- Open the Lovable React source under `lovable/src/...` for the page you are porting.
- Identify the corresponding Vue target under `frontend/app/...`.
- Derive the parity-evidence `<area>/<page>` path from the Vue path by stripping `frontend/app/` and `.vue` and collapsing nested directories. Examples:
  - `frontend/app/pages/login.vue` → `auth/login`
  - `frontend/app/pages/dashboard.vue` → `dashboards/dashboard`
  - `frontend/app/pages/requests/index.vue` → `requests/list`
  - `frontend/app/pages/requests/[id]/index.vue` → `requests/detail`
  - `frontend/app/components/voting/VotingPanel.vue` → `components/voting-panel`

### 2. Launch both apps at matched viewport

- Start the Lovable prototype and the Nuxt app side by side.
- Use dev-browser (per AGENTS.md). Set the viewport to **desktop 1440×900** for the primary capture and **mobile 390×844** for the responsive capture.
- Confirm RTL is the default direction in both apps.

### 3. Screenshot Lovable

- Capture the Lovable page at desktop 1440×900.
- Save as `_bmad-output/parity-evidence/<area>/<page>/lovable.png`.

### 4. Screenshot the current Nuxt page

- Capture the current Nuxt target at desktop 1440×900 **before** your port (this is your "before" baseline; it will be overwritten in step 6).
- Save as `_bmad-output/parity-evidence/<area>/<page>/current.png`.

### 5. Port markup, composables, and stores

- Translate React JSX → Vue template; React hooks → Vue composables and Pinia stores.
- Re-wire data flow to real Laravel APIs (no mock data).
- Mirror layout for RTL using the **RTL Mirror Checklist** below.
- Preserve all Arabic copy verbatim from the prototype.
- Use shadcn-vue primitives as the implementation base; customize to match the screenshot.

### 6. Re-screenshot the current Nuxt page

- After the port, recapture the Nuxt target at desktop 1440×900.
- Overwrite `_bmad-output/parity-evidence/<area>/<page>/current.png` with the post-port capture.

### 7. Produce the `side-by-side.png` composite

- Compose a single PNG with the Lovable capture on the **left** and the current Nuxt capture on the **right**, at identical scale.
- Save as `_bmad-output/parity-evidence/<area>/<page>/side-by-side.png`.
- Any tool is acceptable (ImageMagick `convert +append`, Figma export, manual screenshot tool). Both halves must show the same logical screen state.

### 8. Commit the triplet and request sign-off

- Stage all three files (`lovable.png`, `current.png`, `side-by-side.png`) in the same commit as the Vue/CSS code change.
- All commits stay signed (see AGENTS.md commit rules).
- The story remains `in-progress` until the user has reviewed the `side-by-side.png` and signed off. Only then update the story Status to `review` and run the BMad completion gate.

---

## RTL Mirror Checklist

Apply every item that visually applies to the page you are porting:

- [ ] Sidebar moves to the right edge.
- [ ] Chevrons (`>`) flip to (`<`); back arrows flip direction.
- [ ] Numeric / Latin-only content (currency amounts, request IDs, SWIFT codes) stays LTR-embedded via `<bdo dir="ltr">` or `unicode-bidi: isolate`.
- [ ] Icon glyphs that imply direction (send, reply, undo, forward) flip; semantic icons (user, document, lock, bell) do NOT flip.
- [ ] Form labels right-aligned; inputs receive their value LTR if the content is numeric.
- [ ] Table column order mirrors (first logical column on the right).
- [ ] Step indicators / breadcrumbs read right-to-left.
- [ ] Dropdown / popover anchors flip horizontally (open from right rather than left when at the start of a line).
- [ ] Drawer / off-canvas panels enter from the right.
- [ ] Modal close (`×`) stays at the top-start corner of the modal (top-right in RTL).

---

## Exemption Marker

If your edit truly does not change rendering (comment-only, type-only, prop rename with no visual effect), add `// @parity-exempt` as the **first non-blank line** of the Vue file. The parity-evidence check script (`frontend/scripts/check-parity-evidence.ts`) will skip it.

Do not use the marker to bypass real visual changes. Misuse is a HALT condition during review.

---

## Verification

Before pushing or marking the story complete:

```bash
npx tsx frontend/scripts/check-parity-evidence.ts
```

The script reads the staged file list (`git diff --name-only`), derives the expected `<area>/<page>` paths, and asserts the triplet exists for each one. Pre-push and CI run the same script automatically.

---

## References

- BMad gate: `_bmad/custom/bmad-dev-story.toml` (persistent fact + activation step)
- Skill alias: `.claude/skills/clone-page/SKILL.md`
- Check script: `frontend/scripts/check-parity-evidence.ts`
- Doctrine: `AGENTS.md` (`lovable/` rule), root `CLAUDE.md` (mirror), `docs/04-frontend-guide.md` (Visual Parity Workflow), `DESIGN.md` (Source of truth note)

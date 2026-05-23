# Visual Regression Lock — Story 9.5

This document describes the Playwright visual regression suite introduced in Story 9.5 to prevent future drift on parity-locked pages.

---

## Overview

Every page that reached a `PASS` verdict in `docs/ui-parity/parity-matrix.md` is locked via a Playwright `toHaveScreenshot()` baseline. CI fails any PR that drifts from that baseline without an intentional update.

Currently locked pages (one per PASS row):

| area | page | spec file |
| ---- | ---- | --------- |
| dashboards | data-entry | `frontend/tests/visual/dashboards-data-entry.spec.ts` |

---

## Running Locally

```bash
# Run the visual suite (compare against committed baselines)
npm run test:visual

# Run with a full Playwright report
npx playwright test --project=visual --reporter=html
```

The `test:visual` script maps to `playwright test --project=visual` and runs only the `frontend/tests/visual/` specs.

---

## Updating Baselines Intentionally

When a story **deliberately** changes a parity-locked page, you must update both the Playwright baseline and the parity-evidence triplet in the same PR commit.

### Step 1 — Regenerate the Playwright baseline

```bash
# Update all visual baselines
npm run test:visual:update

# Update a specific spec only
npx playwright test --project=visual --update-snapshots frontend/tests/visual/dashboards-data-entry.spec.ts
```

### Step 2 — Re-capture the parity-evidence triplet

Follow the clone-page workflow in `docs/ui-parity/clone-page-workflow.md` to produce a fresh `current.png` and regenerate `side-by-side.png` for the affected page. Commit both in the same change as the Playwright baseline.

### Dual-update rule (enforced by BMad gate)

Updating the Playwright baseline without updating the evidence triplet is a **HALT condition** in the `bmad-dev-story` workflow. The rule is recorded in `_bmad/custom/bmad-dev-story.toml` as a persistent fact.

---

## Threshold Tuning (AC7 / AC8)

### Chosen value

```ts
maxDiffPixels: 200
```

This is set in the `visual` project block of `frontend/playwright.config.ts`.

### Justification

- A deliberate 4 px padding change on `login.vue` produces a pixel diff of approximately 300–600 px (depending on the element's width and row height). This exceeds the 200 px ceiling and reliably fails.
- OS-level font-rendering noise between macOS (developer laptops) and Ubuntu 22.04 (GitHub Actions runner) typically introduces < 50 px of sub-pixel variance per test run. This stays well below 200 px and does not cause false positives.

### CI font-rendering posture

CI baselines are generated on Linux (Ubuntu 22.04 via `ubuntu-latest` runner). Developers on macOS may see minor rendering differences when running `npm run test:visual` locally — this is expected and does not indicate a real regression. To regenerate baselines for a local OS, use `--update-snapshots` in a throwaway branch and do not commit those baselines.

The recommended approach: always treat CI as the canonical baseline environment.

---

## Troubleshooting Common False Positives

### Font-loading race
**Symptom:** Baseline differs from CI screenshot by a few characters rendered in a fallback font.
**Fix:** The spec calls `await page.evaluate(() => document.fonts.ready)` before `toHaveScreenshot()`. If this still occurs, add `await page.waitForTimeout(200)` after `document.fonts.ready` as a last resort — but first verify the font is being served correctly.

### Animation timing
**Symptom:** Screenshot captures a mid-animation state (e.g., a spinner, a fade-in).
**Fix:** The `visual` Playwright project sets `reducedMotion: 'reduce'` globally. Individual specs also pass `animations: 'disabled'` to `toHaveScreenshot()`. If an animation still fires, it is probably a CSS animation that ignores `prefers-reduced-motion` — add `animation: none !important` to the Nuxt app's test-mode styles.

### OS-level sub-pixel rendering
**Symptom:** Diff is < 50 px and consists of scattered 1-pixel differences across text edges.
**Fix:** This is within the `maxDiffPixels: 200` threshold and should not fail. If it does fail, the threshold may need slight upward adjustment. Document the new value here.

### Sidebar collapse state
**Symptom:** Sidebar renders collapsed in baseline but expanded in new screenshot (or vice versa).
**Fix:** The spec's `openDashboard` helper sets `localStorage.setItem('sidebar_collapsed', 'false')`. Verify that the app reads this key and applies the state before the screenshot fires.

### Nuxt devtools timing badge
**Symptom:** ~200px desktop diff caused by `#vue-tracer-overlay` or `nuxt-devtools-inspect-panel` rendering a millisecond timer that changes every run.
**Fix:** The spec's `DEVTOOLS_MASK` constant masks `#vue-tracer-overlay, nuxt-devtools-inspect-panel, nuxt-devtools-anchor, #nuxt-devtools-container`. This is applied globally to all `toHaveScreenshot()` calls. If Nuxt devtools changes its element IDs in a future version, add the new selector to `DEVTOOLS_MASK`.

---

## Adding a New Parity-Locked Page

When a MINOR_DRIFT or MAJOR_DRIFT row is remediated (via Story 9.3 or 9.4) and re-audited to PASS:

1. Add a row to the `visual_baseline` column in `docs/ui-parity/parity-matrix.md` marking it `LOCKED`.
2. Create `frontend/tests/visual/<area>-<page>.spec.ts` following the pattern in `dashboards-data-entry.spec.ts`.
3. Run `npm run test:visual:update` to generate the baseline.
4. Add a CI path-filter entry if the page lives under a new directory not already covered.
5. Commit spec + baseline + matrix update in a single change.

---

## References

- `docs/ui-parity/parity-matrix.md` — verdict matrix; PASS rows define the lock set
- `docs/ui-parity/clone-page-workflow.md` — parity-evidence capture procedure
- `frontend/playwright.config.ts` — `visual` project configuration
- `_bmad/custom/bmad-dev-story.toml` — BMad persistent facts (dual-update rule)
- Playwright `toHaveScreenshot()` docs: use `npx ctx7@latest docs /microsoft/playwright` for the latest API reference

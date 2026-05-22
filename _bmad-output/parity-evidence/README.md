# Parity Evidence Directory

This directory holds the visual evidence used to gate the Lovable-parity workflow defined in Epic 9 (Stories 9.1–9.5). It is the source of truth that every UI-touching dev story must update — see `docs/ui-parity/clone-page-workflow.md` and the BMad dev-story gate in `_bmad/custom/bmad-dev-story.toml`.

## Directory Structure

```
_bmad-output/parity-evidence/<area>/<page>/
  ├── lovable.png         (or lovable-desktop.png + lovable-mobile.png)
  ├── current.png         (or current-desktop.png + current-mobile.png)
  └── side-by-side.png    (composite — lovable on the left, current on the right)
```

`<area>` and `<page>` are derived from the Vue path under `frontend/app/pages/` by stripping the `frontend/app/` prefix and `.vue` suffix, then collapsing nested directories. The same convention is enforced by `frontend/scripts/check-parity-evidence.ts` (Story 9.1).

Examples:

| Vue file | Evidence directory |
| -------- | ------------------ |
| `frontend/app/pages/login.vue` | `_bmad-output/parity-evidence/auth/login/` |
| `frontend/app/pages/login-otp.vue` | `_bmad-output/parity-evidence/auth/login-otp/` |
| `frontend/app/pages/requests/index.vue` | `_bmad-output/parity-evidence/requests/list/` |
| `frontend/app/pages/requests/[id]/index.vue` | `_bmad-output/parity-evidence/requests/detail/` |
| `frontend/app/pages/requests/new/step-1.vue` | `_bmad-output/parity-evidence/requests/new-step-1/` |
| `frontend/app/pages/admin/banks/index.vue` | `_bmad-output/parity-evidence/admin/banks/` |
| `frontend/app/pages/settings/index.vue` | `_bmad-output/parity-evidence/settings/index/` |

## Filename Convention

Story 9.2 chose the **single-composite** convention for both `lovable.png` and `current.png`:

- `lovable.png` — desktop (1440×900) capture. Mobile coverage is included inline via the existing prototype's responsive rendering when available; pages that have a distinct mobile lovable shot use `lovable-mobile.png` alongside.
- `current.png` — desktop (1440×900) capture of the live Nuxt app.
- `current-mobile.png` — present when a distinct mobile Playwright baseline exists at `frontend/tests/screenshots/<story>/...-mobile-darwin.png`.
- `side-by-side.png` — desktop composite only; mobile composites are generated on demand by `tools/make-side-by-side.py --include-mobile`.

Story 9.5 (visual regression lock) will introduce per-area `golden/` files independently.

## Producing Side-by-Side Composites

The repository does not ship ImageMagick. Use Python with Pillow (already installed in the dev environment):

```bash
python3 tools/make-side-by-side.py \
  --lovable _bmad-output/parity-evidence/<area>/<page>/lovable.png \
  --current _bmad-output/parity-evidence/<area>/<page>/current.png \
  --out     _bmad-output/parity-evidence/<area>/<page>/side-by-side.png
```

For batch operations across an entire epic or remediation story, see `tools/build-parity-evidence.py` — which both copies source PNGs into `<area>/<page>/` and emits the composite. That script was used to materialise the initial evidence set for Story 9.2.

## Source PNG Provenance

For Story 9.2's initial verdict matrix the source PNGs were derived as follows (no fresh browser captures were taken):

- **Lovable side** — files under `lovable/screenshots/<ROLE>/*.png`, organised by role and page name. Where the same page exists for multiple roles (e.g. `dashboard.png` or `requests-list.png`), the matrix selects the most canonical or visually-richest variant (typically `CBY_ADMIN/` for shared screens).
- **Current side** — files under `frontend/tests/screenshots/<story>/...-{desktop,mobile}-darwin.png`, captured by the Epic 7 Playwright parity specs (`frontend/tests/e2e/7-*-parity.spec.ts`). These are deterministic, version-controlled artifacts of the live Nuxt app at the commit those specs last passed against.
- **Side-by-side** — generated locally by `tools/build-parity-evidence.py` against the two source PNGs.

Fresh captures (mobile Lovable, undocumented page variants) are out of scope for Story 9.2; they are tracked in the matrix as `MISSING` rows and will be addressed by Stories 9.3 / 9.4 during remediation, or by Story 9.5 when the visual regression suite is introduced.

## Demo-Only Exclusions

The following Lovable surfaces are explicitly **excluded** from parity work (see Story 9.2 AC7 and `AGENTS.md` §Prototype-Only Demo Features):

- The Lovable login screen's role-picker shortcut buttons.
- The header `RoleSwitcher` component.
- `lovable/src/lib/mock.ts` / `lovable/src/lib/governance.ts` mock-state editing tools.
- Demo reset tools in settings (settings tab labelled "Demo Controls" in the prototype).
- The prototype-footer "Demo Environment" banner.

Their lovable screenshots receive `SKIP — demo-only` rows in the matrix with a one-line justification and are not assigned remediation work.

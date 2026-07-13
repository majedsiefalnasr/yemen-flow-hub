# M2 — Deployment Environment Gates (H6, Approved: Option A)

**Status:** Locked. Flag-only enforcement is **not** the acceptable final posture.
Mandatory environment hard-stops are added to Phase A / pre-production. No code
changed yet. Evidence date: 2026-07-11.

**Severity:** H6 recorded as **High** security hardening finding — either bypass
flag can cause complete authentication or identity compromise when misconfigured.
Roadmap tier: **Phase A / Pre-production, must be fixed before go-live.**

---

## 1. Verified current behavior (flag-only, no environment cross-check)

| Flag                                                          | Source / default                                            | Local value | Enforcement                                                                                                                               | Bypass if true in prod                                                          |
| ------------------------------------------------------------- | ----------------------------------------------------------- | ----------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| `APP_DEMO_ROLE_SWITCH` → `demo.allow_role_switch`             | `config/demo.php`, default **false**                        | **true**    | `AuthController` demo-user list + `switch-demo-role` gate on this boolean **only** (`AuthController.php:325,360,419`); no `APP_ENV` check | Any user lists all users and switches into any identity/role — full auth bypass |
| `NUXT_PUBLIC_VISUAL_BYPASS`                                   | `nuxt.config.ts:65`, default **false** (unset in FE `.env`) | off         | `00.visual-bypass.global.ts:19` fabricates an authenticated CBY_ADMIN when true; no production hard-stop                                  | Unauthenticated visitor becomes CBY_ADMIN in the SPA                            |
| `demo.allowed_environments` = `['local','staging','testing']` | hard-coded in `config/demo.php`                             | —           | Guards **seeding only** (`GuardsDemoSeedEnvironment`), not auth-switch endpoints                                                          | Synthetic data seeded into staging; data-integrity risk, not direct prod bypass |
| `APP_ENV`                                                     | `.env`                                                      | **local**   | Debug + intended env signal; **not consulted** by the two bypass gates                                                                    | Other guards that should key on it don't                                        |

Root problem: the two auth-bypass flags trust a single boolean / build flag with
**no environment cross-check**. One copied env var or wrong build flag silently
disables authentication.

## 2. Deployment values — UNVERIFIED ASSUMPTIONS (inspect before go-live)

Actual deployed values are **not** confirmable from the repository. Record the
following as a **deployment verification requirement**, marked unverified until a
deploy inspection confirms them — not as confirmed-safe values.

**Production (required):**

```text
APP_ENV=production
APP_DEMO_ROLE_SWITCH=false
NUXT_PUBLIC_VISUAL_BYPASS  unset or false
Demo seed execution unavailable regardless of other config
```

**Staging (safe default):**

```text
APP_DEMO_ROLE_SWITCH=false
NUXT_PUBLIC_VISUAL_BYPASS  unset or false
```

Temporary staging enablement is allowed **only** through an explicit, documented
decision with restricted access and clear warnings. It must never be inherited
automatically from local development configuration.

## 3. Phase A tasks (approved; not yet implemented)

### A-ENV-1 — Backend demo hard-stop (fail closed in production)

- Demo identity-switching endpoints must be inaccessible in production **even if `APP_DEMO_ROLE_SWITCH=true`**.
- Require **both**: (1) the demo feature flag is explicitly enabled, **and** (2) the current app environment is explicitly approved for demo switching (`demo.allowed_environments`, production permanently excluded).
- Reject **before** listing demo users, switching identity, or changing roles — not merely hiding the feature.
- **Centralize** the guard (middleware or a single gate) applied to every demo endpoint (`demoUsers`, `switch-demo-role`, and the third gated method at `AuthController.php:419`), rather than three per-method boolean checks that can drift.
- Production always fails closed.

### A-ENV-2 — Frontend visual-bypass hard-stop (defense in depth)

- `NUXT_PUBLIC_VISUAL_BYPASS` must be **impossible to activate in a production build**.
- Fail the production build or startup when the flag is enabled.
- Prevent the middleware from fabricating a user when running in production (guard on build/runtime environment, not just the flag).
- Display a prominent development-only warning when the bypass is active in an allowed non-production environment.
- The fabricated identity must never replace backend authentication/authorization — the backend continues to reject unauthenticated API requests even when visual bypass is active locally.

### A-ENV-3 — Seeding gate review (data-integrity, kept separate from auth)

- Keep `demo.allowed_environments` distinct from authentication-bypass controls.
- Production permanently excluded from seeding.
- Staging seeding requires explicit intent; must not run automatically as part of a normal deployment.
- Demo data must be clearly identifiable; a destructive reset/cleanup procedure must exist for non-production environments.
- Staging appearing in the allowed list is not automatically a defect but requires deployment controls + documentation.

## 4. Required regression cases (must pass before go-live)

1. Production + demo-switch flag **false** → denied.
2. Production + demo-switch flag **true** → **still denied** (the core hard-stop proof).
3. Approved local/test environment + flag false → denied.
4. Approved local/test environment + flag true → allowed only for the intended test flow.
5. Production frontend build + visual bypass true → build or startup **fails**.
6. Local frontend + visual bypass true → visual mode may activate, but protected APIs still require valid backend authentication.
7. Production demo-seed attempt → blocked.

## 5. Dependencies and links

- Explains M1 §9: `demo.seed_data` default `true` + `allowed_environments` including `staging` is how the 48 synthetic requests reach non-local environments.
- Deployment-verification requirement (§2) is a go-live checklist item, owned by ops, not a code change.
